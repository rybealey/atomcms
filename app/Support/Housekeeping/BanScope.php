<?php

namespace App\Support\Housekeeping;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Active account bans as a query fragment, per emulator driver. Plus keys bans
 * by bantype with the username in `value`; Arcturus stores user_id/ban_expire.
 */
class BanScope
{
    /**
     * Constrain a users query to players with (or without) an active ban.
     *
     * @param  Builder<User>  $query
     *
     * @return Builder<User>
     */
    public static function apply(Builder $query, bool $banned = true): Builder
    {
        $active = function (QueryBuilder $sub): void {
            $sub->selectRaw('1')->from('bans');

            if (PlayerColumns::isPlus()) {
                $sub->whereColumn('bans.value', 'users.username')
                    ->where('bans.bantype', 'user')
                    ->where('bans.expire', '>', time());
            } else {
                $sub->whereColumn('bans.user_id', 'users.id')
                    ->where('bans.ban_expire', '>', time());
            }
        };

        return $banned ? $query->whereExists($active) : $query->whereNotExists($active);
    }

    public static function activeCount(): int
    {
        return PlayerColumns::isPlus()
            ? DB::table('bans')->where('expire', '>', time())->count()
            : DB::table('bans')->where('ban_expire', '>', time())->count();
    }

    public static function expiringWithin(int $seconds): int
    {
        $column = PlayerColumns::isPlus() ? 'expire' : 'ban_expire';

        return DB::table('bans')
            ->where($column, '>', time())
            ->where($column, '<=', time() + $seconds)
            ->count();
    }

    /**
     * Every ban ever recorded against the player, newest first.
     *
     * @return list<array{reason: string, expires_at: int, added_by: string, added_at: int, active: bool}>
     */
    public static function historyFor(int $userId, string $username): array
    {
        if (PlayerColumns::isPlus()) {
            return DB::table('bans')
                ->where('bantype', 'user')
                ->where('value', $username)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (object $ban): array => [
                    'reason' => (string) $ban->reason,
                    'expires_at' => (int) $ban->expire,
                    'added_by' => (string) $ban->added_by,
                    'added_at' => (int) $ban->added_date,
                    'active' => (float) $ban->expire > time(),
                ])
                ->values()
                ->all();
        }

        return DB::table('bans')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (object $ban): array => [
                'reason' => (string) $ban->ban_reason,
                'expires_at' => (int) $ban->ban_expire,
                'added_by' => (string) $ban->user_staff_id,
                'added_at' => (int) $ban->timestamp,
                'active' => (int) $ban->ban_expire > time(),
            ])
            ->values()
            ->all();
    }
}
