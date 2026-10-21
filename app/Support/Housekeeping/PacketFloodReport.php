<?php

namespace App\Support\Housekeeping;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * pixelrp: what the housekeeping player page shows of the account's
 * disconnects for flooding the game server with packets - far more of one
 * kind than the client ever sends, which is a packet tool or a bot. The
 * emulator writes one row per disconnect, for whichever character was playing
 * (PacketFloodLog, rp_packet_flood_log, migration 291), so every character on
 * the account is read.
 */
final class PacketFloodReport
{
    private const MAX_ROWS = 25;

    /**
     * The account's flood disconnects, latest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function disconnects(User $account): array
    {
        $root = (int) ($account->parent_id ?: $account->id);

        return DB::table('rp_packet_flood_log as f')
            ->join('users as u', 'u.id', '=', 'f.user_id')
            ->where(fn (Builder $query) => $query->where('u.id', $root)->orWhere('u.parent_id', $root))
            ->orderByDesc('f.created_at')
            ->limit(self::MAX_ROWS)
            ->get(['f.username', 'f.packet', 'f.dropped', 'f.window_seconds', 'f.created_at'])
            ->map(fn (object $row): array => [
                'character' => (string) $row->username,
                'packet' => (string) $row->packet,
                'dropped' => (int) $row->dropped,
                'window' => (int) $row->window_seconds,
                'at' => (int) $row->created_at,
            ])
            ->values()
            ->all();
    }
}
