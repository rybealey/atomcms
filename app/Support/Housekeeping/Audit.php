<?php

namespace App\Support\Housekeeping;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * The staff audit trail: every write the panel makes lands in activity_log
 * under the `housekeeping` log, with who did it, to whom, the values before
 * and after, the reason they gave and, for Live actions, what the emulator
 * said. The Staff audit screen, the log explorer and a record's field history
 * all read these rows back.
 */
final class Audit
{
    public const LOG = 'housekeeping';

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array{type: string, id: int|string, label?: string}|null  $target  a non-user subject (a crime, a setting, a ban)
     */
    public static function record(
        string $event,
        string $summary,
        ?int $playerId = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        ?HotelResult $rcon = null,
        ?array $target = null,
        ?Model $subject = null,
        string $path = 'saved',
    ): Activity {
        $actor = auth()->user();
        $properties = array_filter([
            // live, saved or game: how the change reached the hotel.
            'path' => $rcon !== null ? 'live' : $path,
            'player_id' => $playerId,
            'target' => $target,
            'before' => $before === [] ? null : $before,
            'after' => $after === [] ? null : $after,
            'reason' => $reason,
            'rcon' => $rcon?->toArray(),
        ], fn (mixed $value): bool => $value !== null);

        $entry = activity(self::LOG)->event($event)->withProperties($properties);

        if ($actor instanceof User) {
            $entry->causedBy($actor);
        }

        if ($subject !== null) {
            $entry->performedOn($subject);
        } elseif ($playerId !== null) {
            $player = User::query()->find($playerId);

            if ($player instanceof User) {
                $entry->performedOn($player);
            }
        }

        /** @var Activity $logged */
        $logged = $entry->log($summary);

        return $logged;
    }

    /**
     * Only the fields that changed, as before/after pairs.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            if (! array_key_exists($key, $before) || $before[$key] != $value) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $value;
            }
        }

        return [$old, $new];
    }
}
