<?php

namespace App\Support\Housekeeping;

use Illuminate\Support\Facades\DB;

/**
 * Player chips everywhere a user id appears: one query for a page of rows,
 * id => {id, username, look}. Unknown ids (deleted accounts, the system
 * account) come back as null and draw as a plain name.
 */
final class PlayerRefs
{
    /**
     * @param  iterable<mixed>  $ids
     *
     * @return array<int, array{id: int, username: string, look: string}>
     */
    public static function many(iterable $ids): array
    {
        $wanted = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $wanted[$id] = true;
            }
        }

        if ($wanted === []) {
            return [];
        }

        $refs = [];

        foreach (array_chunk(array_keys($wanted), 500) as $chunk) {
            foreach (DB::table('users')->whereIn('id', $chunk)->get(['id', 'username', 'look']) as $row) {
                $refs[(int) $row->id] = ['id' => (int) $row->id, 'username' => (string) $row->username, 'look' => (string) $row->look];
            }
        }

        return $refs;
    }

    /**
     * @param  array<int, array{id: int, username: string, look: string}>  $refs
     *
     * @return array{id: int, username: string, look: string}|null
     */
    public static function pick(array $refs, mixed $id, ?string $fallbackName = null): ?array
    {
        $id = (int) $id;

        if (isset($refs[$id])) {
            return $refs[$id];
        }

        return $fallbackName !== null && $fallbackName !== '' ? ['id' => $id, 'username' => $fallbackName, 'look' => ''] : null;
    }
}
