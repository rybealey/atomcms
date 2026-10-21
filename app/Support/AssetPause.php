<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * pixelrp: the browser guard's pause on game files (docs/asset-protection-plan.md).
 *
 * When the client's browser guard sends a player back to the website -
 * DevTools opened, or an automated browser - the emulator writes it down in
 * rp_browser_guard_log (BrowserGuardLog, migration 259). For a while after
 * that the ACCOUNT gets no game files at all: /game shows the ordinary
 * "Connection lost" screen instead of the client (it never says why), no
 * fresh asset pass is issued, and nginx turns away every file the
 * pass it already holds asks for, from any page on the site - so a script
 * pasted into DevTools on the website can't carry on where the game left off.
 *
 * nginx learns who is paused by asking GET /api/internal/asset-pauses every
 * few seconds (docker/web/njs/asset-limit.js), so this table stays the one
 * place it is decided and a restart of either side loses nothing.
 *
 * The guard log is per character; the pause is per account (the root, as
 * the asset pass is). The staff tier (Developers, AssetPass::UNLIMITED_RANK)
 * is never paused, so a row from before a promotion can't lock one out. Other
 * staff can be: the client skips the guard for Administrators too, but
 * Builders up to Moderators run it like any player.
 *
 * A ban on any character of the account pauses it too, until the ban ends -
 * for every rank. Without it a pass already issued kept working for its whole
 * TTL after the ban, and a ban on a second character was never seen at all
 * (check.ban reads the root's name only).
 */
final class AssetPause
{
    public static function seconds(): int
    {
        return max(60, (int) config('services.asset_pass.guard_pause_minutes') * 60);
    }

    /** Unix time the account's pause ends, or null when it isn't paused. */
    public static function until(User $account): ?int
    {
        $banned = self::bans($account->id)[$account->id] ?? null;
        $guarded = self::guardUntil($account);

        return ($banned === null && $guarded === null) ? null : max((int) $banned, (int) $guarded);
    }

    /** @return array<int, int> Every paused account => the unix time its pause ends. */
    public static function all(): array
    {
        $paused = self::guardAll();

        foreach (self::bans() as $account => $until) {
            $paused[$account] = max($paused[$account] ?? 0, $until);
        }

        return $paused;
    }

    /**
     * Accounts with a character under an active user ban => when the last of
     * those bans ends. Optionally for one account only.
     *
     * @return array<int, int>
     */
    private static function bans(?int $account = null): array
    {
        // Two reads rather than a join: bans.value and users.username are in
        // different character sets, and Plus keys a user ban by the name.
        try {
            $query = DB::table('bans')->where('bantype', 'user')->where('expire', '>', time());

            if ($account !== null) {
                $query->whereIn('value', DB::table('users')->where('id', $account)->orWhere('parent_id', $account)->pluck('username'));
            }

            $bans = $query->get(['value', 'expire']);

            if ($bans->isEmpty()) {
                return [];
            }

            $accounts = DB::table('users')
                ->whereIn('username', $bans->pluck('value')->unique()->values())
                ->get(['username', 'id', 'parent_id'])
                ->mapWithKeys(fn (object $user): array => [mb_strtolower((string) $user->username) => (int) ($user->parent_id ?: $user->id)]);
        } catch (QueryException $e) {
            Log::warning('AssetPause: could not read bans', ['error' => $e->getMessage()]);

            return [];
        }

        $banned = [];

        foreach ($bans as $ban) {
            $root = $accounts[mb_strtolower((string) $ban->value)] ?? null;

            if ($root !== null) {
                $banned[$root] = max($banned[$root] ?? 0, (int) $ban->expire);
            }
        }

        return $banned;
    }

    private static function guardUntil(User $account): ?int
    {
        if (AssetPass::tier($account) === AssetPass::TIER_STAFF) {
            return null;
        }

        try {
            $latest = DB::table('rp_browser_guard_log as g')
                ->join('users as u', 'u.id', '=', 'g.user_id')
                ->where(fn (Builder $query) => $query->where('u.id', $account->id)->orWhere('u.parent_id', $account->id))
                ->where('g.created_at', '>', time() - self::seconds())
                ->max('g.created_at');
        } catch (QueryException $e) {
            Log::warning('AssetPause: could not read rp_browser_guard_log', ['error' => $e->getMessage()]);

            return null;
        }

        return $latest === null ? null : (int) $latest + self::seconds();
    }

    /** @return array<int, int> Every account the guard paused => the unix time that pause ends. */
    private static function guardAll(): array
    {
        try {
            $rows = DB::select(
                'SELECT a.id AS account, MAX(g.created_at) AS latest
                   FROM rp_browser_guard_log g
                   JOIN users u ON u.id = g.user_id
                   JOIN users a ON a.id = COALESCE(u.parent_id, u.id)
                  WHERE g.created_at > ? AND a.`rank` < ?
                  GROUP BY a.id',
                [time() - self::seconds(), AssetPass::UNLIMITED_RANK],
            );
        } catch (QueryException $e) {
            Log::warning('AssetPause: could not read rp_browser_guard_log', ['error' => $e->getMessage()]);

            return [];
        }

        $paused = [];

        foreach ($rows as $row) {
            $paused[(int) $row->account] = (int) $row->latest + self::seconds();
        }

        return $paused;
    }
}
