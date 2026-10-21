<?php

namespace App\Support\Housekeeping;

use App\Services\Roleplay\RoleplaySchema;
use Illuminate\Support\Facades\DB;

/**
 * Room chips: id => title for a page of rows, in one query.
 */
final class RoomRefs
{
    /**
     * @param  iterable<mixed>  $ids
     *
     * @return array<int, string>
     */
    public static function many(iterable $ids): array
    {
        $wanted = [];

        foreach ($ids as $id) {
            if ((int) $id > 0) {
                $wanted[(int) $id] = true;
            }
        }

        if ($wanted === []) {
            return [];
        }

        $title = RoleplaySchema::roomTitleColumn();

        return DB::table('rooms')
            ->whereIn('id', array_keys($wanted))
            ->pluck($title, 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }
}
