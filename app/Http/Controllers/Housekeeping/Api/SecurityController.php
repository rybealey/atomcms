<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\Logs\LogSources;
use App\Support\Housekeeping\PlayerColumns;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Security: browser-guard flags and groups of accounts linked by an IP or a
 * device. A group is every account that shares one of those with another, so
 * a household of three and a farm of ten each read as one row.
 */
class SecurityController extends Controller
{
    /** Links from further back than this are history, not something to review. */
    private const WINDOW = 30 * 86400;

    public function __invoke(Request $request): JsonResponse
    {
        $show = (string) $request->query('show', 'review');
        $now = time();
        $groups = $this->groups();
        $reviews = RoleplaySchema::has('website_housekeeping_link_reviews') ? DB::table('website_housekeeping_link_reviews')->get() : collect();
        $reviewed = [];

        foreach ($reviews as $r) {
            $reviewed[$r->account_id . ':' . $r->other_account_id] = (string) $r->status;
        }

        $ids = array_merge(...array_map(fn (array $g): array => $g['accounts'], $groups ?: [['accounts' => []]]));
        $users = $ids !== [] ? DB::table('users')->whereIn('id', $ids)->get(['id', 'username', 'look', 'account_created', 'last_online', 'online'])->keyBy('id') : collect();
        $banned = PlayerColumns::isPlus() && $users->isNotEmpty() ? array_flip(array_map('strtolower', DB::table('bans')->where('bantype', 'user')->where('expire', '>', $now)->whereIn('value', $users->pluck('username'))->pluck('value')->all())) : [];
        $flagged = RoleplaySchema::has('rp_browser_guard_log') && $ids !== [] ? DB::table('rp_browser_guard_log')->whereIn('user_id', $ids)->where('created_at', '>=', $now - 7 * 86400)->pluck('user_id')->flip() : collect();
        $out = [];

        foreach ($groups as $g) {
            $pairs = 0;
            $household = 0;
            $dismissed = 0;

            foreach ($g['accounts'] as $a) {
                foreach ($g['accounts'] as $b) {
                    if ($a < $b) {
                        $pairs++;
                        $s = $reviewed[$a . ':' . $b] ?? null;
                        $household += $s === 'household' ? 1 : 0;
                        $dismissed += $s === 'dismissed' ? 1 : 0;
                    }
                }
            }

            $status = $household === $pairs ? 'household' : ($household + $dismissed === $pairs ? 'dismissed' : 'flagged');
            $bannedCount = count(array_filter($g['accounts'], fn (int $id): bool => isset($banned[strtolower((string) ($users->get($id)->username ?? ''))])));

            if ($show === 'review' && $status !== 'flagged') {
                continue;
            }

            if ($show === 'household' && $status !== 'household') {
                continue;
            }

            $names = array_values(array_filter(array_map(fn (int $id): ?string => $users->get($id)->username ?? null, $g['accounts'])));
            $out[] = [
                'key' => implode('-', $g['accounts']),
                'names' => count($names) > 3 ? sprintf('%s and %d more', implode(', ', array_slice($names, 0, 2)), count($names) - 2) : (count($names) > 1 ? implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names) : ($names[0] ?? '')),
                'via' => implode(' · ', array_filter([
                    $g['devices'] !== [] ? (count($g['devices']) === 1 ? 'Device ' . substr($g['devices'][0], 0, 4) . '…' . substr($g['devices'][0], 4, 4) : count($g['devices']) . ' shared devices') : null,
                    $g['ips'] !== [] ? (count($g['ips']) === 1 ? 'IP ' . $g['ips'][0] : count($g['ips']) . ' shared IPs') : null,
                ])),
                'status' => $status,
                'banned' => $bannedCount,
                'last_seen' => $g['last_seen'],
                'accounts' => array_values(array_map(function (int $id) use ($users, $banned, $flagged, $now): array {
                    $u = $users->get($id);
                    $isBanned = $u !== null && isset($banned[strtolower((string) $u->username)]);
                    $new = $u !== null && (int) $u->account_created >= $now - 7 * 86400;

                    return [
                        'player' => $u !== null ? ['id' => (int) $u->id, 'username' => (string) $u->username, 'look' => (string) $u->look] : null,
                        'created' => $u !== null ? (int) $u->account_created : null,
                        'last_online' => $u !== null ? ((string) $u->online === '1' ? $now : (int) $u->last_online) : null,
                        'state' => $isBanned ? 'Banned' : ($flagged->has($id) ? 'Review' : ($new ? 'New' : 'Clean')),
                    ];
                }, $g['accounts'])),
            ];
        }

        usort($out, fn (array $a, array $b): int => [$b['banned'] > 0, $b['last_seen']] <=> [$a['banned'] > 0, $a['last_seen']]);
        $today = strtotime('today');
        $firstLogins = RoleplaySchema::has('website_login_logs')
            ? DB::table('website_login_logs')->where('succeeded', true)->whereNotNull('user_id')->groupBy('user_id')->havingRaw('MIN(created_at) >= ?', [date('Y-m-d H:i:s', (int) $today)])->selectRaw('user_id, MIN(ip_address) AS ip')->get()
            : collect();
        $knownIps = $firstLogins->isNotEmpty() ? DB::table('website_login_logs')->whereIn('ip_address', $firstLogins->pluck('ip'))->where('created_at', '<', date('Y-m-d H:i:s', (int) $today))->distinct()->count('ip_address') : 0;
        $guard = RoleplaySchema::has('rp_browser_guard_log') ? DB::table('rp_browser_guard_log')->where('created_at', '>=', $now - 7 * 86400)->orderByDesc('created_at')->limit(30)->get() : collect();
        $refs = PlayerRefs::many($guard->pluck('user_id'));

        return response()->json([
            'stats' => [
                'open_flags' => $guard->pluck('user_id')->unique()->count(),
                'oldest_flag' => $guard->min('created_at') !== null ? (int) $guard->min('created_at') : null,
                'groups' => count($groups),
                'groups_banned' => count(array_filter($out, fn (array $g): bool => $g['banned'] > 0)),
                'first_logins' => $firstLogins->count(),
                'first_known_ip' => $knownIps,
                'failed_hour' => RoleplaySchema::has('website_login_logs') ? DB::table('website_login_logs')->where('succeeded', false)->where('created_at', '>=', date('Y-m-d H:i:s', $now - 3600))->count() : 0,
            ],
            'groups' => array_slice($out, 0, 100),
            'flags' => $guard->map(fn (stdClass $g): array => [
                'id' => (int) $g->id,
                'player' => PlayerRefs::pick($refs, $g->user_id, (string) $g->username),
                'reason' => LogSources::guardReason((int) $g->reason),
                'detail' => (string) $g->detail,
                'at' => (int) $g->created_at,
            ])->values(),
        ]);
    }

    /**
     * Mark every pair in a group a household or dismissed, with a note.
     */
    public function review(Request $request): JsonResponse
    {
        $data = $request->validate([
            'accounts' => ['required', 'array', 'min:2', 'max:50'],
            'accounts.*' => ['integer', 'min:1'],
            'status' => ['required', 'in:household,dismissed,flagged'],
            'note' => ['required', 'string', 'min:3', 'max:255'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $ids = array_values(array_unique(array_map('intval', $data['accounts'])));

        foreach ($ids as $a) {
            foreach ($ids as $b) {
                if ($a === $b) {
                    continue;
                }

                if ($data['status'] === 'flagged') {
                    DB::table('website_housekeeping_link_reviews')->where('account_id', $a)->where('other_account_id', $b)->delete();

                    continue;
                }

                DB::table('website_housekeeping_link_reviews')->updateOrInsert(
                    ['account_id' => $a, 'other_account_id' => $b],
                    ['status' => $data['status'], 'reviewed_by' => $actor->id, 'note' => $data['note'], 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        $names = DB::table('users')->whereIn('id', $ids)->pluck('username')->implode(', ');
        Audit::record('link_review', sprintf('marked %s as %s', $names, match ($data['status']) {
            'household' => 'a household', 'dismissed' => 'not linked', default => 'flagged'
        }), $ids[0], target: ['type' => 'link_group', 'id' => implode('-', $ids)], after: ['status' => $data['status'], 'accounts' => $ids], reason: $data['note']);

        return response()->json(['message' => match ($data['status']) {
            'household' => 'Marked as a household', 'dismissed' => 'Dismissed', default => 'Flagged again'
        }]);
    }

    /**
     * Account groups joined by a shared public IP or device in the last 30
     * days (website_connection_logs), merged with union-find.
     *
     * @return list<array{accounts: list<int>, ips: list<string>, devices: list<string>, last_seen: int}>
     */
    private function groups(): array
    {
        if (! RoleplaySchema::has('website_connection_logs')) {
            return [];
        }

        $since = date('Y-m-d H:i:s', time() - self::WINDOW);
        $shared = DB::table('website_connection_logs')
            ->where('last_seen_at', '>=', $since)
            ->whereIn('ip_address', DB::table('website_connection_logs')->where('last_seen_at', '>=', $since)->groupBy('ip_address')->havingRaw('COUNT(DISTINCT user_id) > 1')->select('ip_address'))
            ->orWhereIn('device_id', DB::table('website_connection_logs')->where('last_seen_at', '>=', $since)->groupBy('device_id')->havingRaw('COUNT(DISTINCT user_id) > 1')->select('device_id'))
            ->limit(5000)
            ->get(['user_id', 'ip_address', 'device_id', 'last_seen_at']);

        $parent = [];
        $find = function (int $x) use (&$parent, &$find): int {
            $parent[$x] ??= $x;

            return $parent[$x] === $x ? $x : ($parent[$x] = $find($parent[$x]));
        };
        $byIp = [];
        $byDevice = [];

        foreach ($shared as $row) {
            $public = filter_var($row->ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

            if ($public) {
                $byIp[(string) $row->ip_address][] = (int) $row->user_id;
            }

            $byDevice[(string) $row->device_id][] = (int) $row->user_id;
        }

        foreach ([$byIp, $byDevice] as $index) {
            foreach ($index as $users) {
                $users = array_values(array_unique($users));

                for ($i = 1; $i < count($users); $i++) {
                    $parent[$find($users[$i])] = $find($users[0]);
                }
            }
        }

        $groups = [];

        foreach ($shared as $row) {
            $root = $find((int) $row->user_id);
            $g = $groups[$root] ?? ['accounts' => [], 'ips' => [], 'devices' => [], 'last_seen' => 0];
            $g['accounts'][(int) $row->user_id] = true;
            $g['last_seen'] = max($g['last_seen'], Epoch::of($row->last_seen_at));

            if (count(array_unique($byIp[(string) $row->ip_address] ?? [])) > 1) {
                $g['ips'][(string) $row->ip_address] = true;
            }

            if (count(array_unique($byDevice[(string) $row->device_id] ?? [])) > 1) {
                $g['devices'][(string) $row->device_id] = true;
            }

            $groups[$root] = $g;
        }

        return array_values(array_filter(array_map(fn (array $g): array => [
            'accounts' => array_map('intval', array_keys($g['accounts'])),
            'ips' => array_keys($g['ips']),
            'devices' => array_keys($g['devices']),
            'last_seen' => $g['last_seen'],
        ], $groups), fn (array $g): bool => count($g['accounts']) > 1));
    }
}
