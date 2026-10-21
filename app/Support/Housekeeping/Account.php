<?php

namespace App\Support\Housekeeping;

use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * One login can own up to three characters (users.parent_id points a
 * character at the account it belongs to; the account row is a character
 * too). Discipline, notes and devices belong to the account; everything else
 * to the character.
 */
final class Account
{
    public static function rootId(User|int $user): int
    {
        if ($user instanceof User) {
            $parent = RoleplaySchema::hasColumn('users', 'parent_id') ? (int) $user->getAttribute('parent_id') : 0;

            return $parent > 0 ? $parent : (int) $user->id;
        }

        if (! RoleplaySchema::hasColumn('users', 'parent_id')) {
            return $user;
        }

        $parent = (int) DB::table('users')->where('id', $user)->value('parent_id');

        return $parent > 0 ? $parent : $user;
    }

    /**
     * Every character on the account, the account row first.
     *
     * @return Collection<int, stdClass>
     */
    public static function characters(int $rootId): Collection
    {
        $query = DB::table('users')->select(['id', 'username', 'look', 'online', 'last_online']);

        if (RoleplaySchema::hasColumn('users', 'parent_id')) {
            $query->where(fn ($where) => $where->where('id', $rootId)->orWhere('parent_id', $rootId))
                ->orderByRaw('id = ? DESC', [$rootId])
                ->orderBy('id');
        } else {
            $query->where('id', $rootId);
        }

        return $query->get();
    }

    /**
     * @return list<int>
     */
    public static function characterIds(int $rootId): array
    {
        return array_values(self::characters($rootId)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * @return list<string>
     */
    public static function usernames(int $rootId): array
    {
        return array_values(self::characters($rootId)->pluck('username')->map(fn (mixed $name): string => (string) $name)->all());
    }
}
