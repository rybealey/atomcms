<?php

namespace App\Support\Housekeeping\Logs;

use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\RoomRefs;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * One newest-first stream over many log tables. Each source is asked for a
 * page past the cursor with its own index; the pages are merged and cut, and
 * the last row kept becomes the next cursor: (time, source position, id), so
 * rows that share a second never repeat or vanish between pages.
 */
final class LogStream
{
    /**
     * @param  array<string, LogSource>  $sources
     * @param  array{player?: int|null, room?: int|null, text?: string|null, from?: int|null, to?: int|null}  $filters
     * @param  array{t: int, s: int, i: int}|null  $cursor
     *
     * @return array{rows: list<array<string, mixed>>, next: string|null}
     */
    public static function page(array $sources, array $filters, ?array $cursor, int $limit = 50): array
    {
        $order = array_keys(LogSources::all());
        $collected = [];

        foreach ($sources as $key => $source) {
            $position = array_search($key, $order, true);
            $position = $position === false ? 0 : (int) $position;

            // A room filter only applies to sources that know their room.
            if (($filters['room'] ?? null) && $source->room === null) {
                continue;
            }

            $query = self::query($source, $filters);

            if ($cursor !== null) {
                self::applyCursor($query, $source, $cursor, $position);
            }

            foreach ($query->limit($limit + 1)->get() as $row) {
                $collected[] = ['t' => (int) $row->_t, 's' => $position, 'i' => (int) $row->id, 'source' => $source, 'row' => $row];
            }
        }

        usort($collected, fn (array $a, array $b): int => [$b['t'], $a['s'], $b['i']] <=> [$a['t'], $b['s'], $a['i']]);
        $more = count($collected) > $limit;
        $collected = array_slice($collected, 0, $limit);

        $playerIds = [];
        $roomIds = [];

        foreach ($collected as $entry) {
            foreach ($entry['source']->players as $column) {
                $playerIds[] = $entry['row']->{$column};
            }

            if ($entry['source']->room !== null) {
                $roomIds[] = $entry['row']->{$entry['source']->room};
            }
        }

        $refs = PlayerRefs::many($playerIds);
        $rooms = RoomRefs::many($roomIds);
        $rows = [];

        foreach ($collected as $entry) {
            /** @var LogSource $source */
            $source = $entry['source'];
            $described = ($source->describe)($entry['row'], $refs, $rooms, ($filters['player'] ?? null) !== null ? (int) $filters['player'] : null);
            $parts = [];

            foreach ($described['parts'] as $part) {
                if (is_array($part) && isset($part['player'])) {
                    $ref = PlayerRefs::pick($refs, $part['player'], $part['name'] ?? null);
                    $parts[] = $ref !== null ? ['player' => $ref] : ['text' => (string) ($part['name'] ?? 'someone')];
                } elseif (is_array($part)) {
                    $parts[] = $part;
                } else {
                    $parts[] = ['text' => (string) $part];
                }
            }

            $rows[] = [
                'key' => $source->key . ':' . $entry['i'],
                'source' => $source->key,
                'kind' => $source->kind,
                'at' => $entry['t'],
                'parts' => $parts,
                'amount' => $described['amount'] ?? null,
                'ledger' => isset($described['ledger']) ? ['other' => PlayerRefs::pick($refs, $described['ledger']['other'] ?? 0)] + $described['ledger'] : null,
                'path' => $described['path'] ?? null,
                'raw' => array_map(fn (mixed $value): string => is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value), $described['raw'] ?? []) + ['time' => $entry['t'] . ' · ' . date('M j, Y g:i A', $entry['t'])],
            ];
        }

        $last = end($collected);

        return [
            'rows' => $rows,
            'next' => $more && $last !== false ? self::encode(['t' => $last['t'], 's' => $last['s'], 'i' => $last['i']]) : null,
        ];
    }

    /**
     * @param  array{player?: int|null, room?: int|null, text?: string|null, from?: int|null, to?: int|null}  $filters
     */
    public static function query(LogSource $source, array $filters): Builder
    {
        $id = 'id';
        $query = DB::table($source->table())
            ->select($source->columns)
            // The time column comes from LogSources' fixed definitions, never from a request.
            /** @phpstan-ignore argument.type */
            ->addSelect(new Expression(self::epoch($source) . ' AS _t'))
            ->orderByDesc($source->time)
            ->orderByDesc($id);

        if ($source->scope !== null) {
            ($source->scope)($query);
        }

        $player = (int) ($filters['player'] ?? 0);

        if ($player > 0) {
            if ($source->forPlayer !== null) {
                ($source->forPlayer)($query, $player);
            } else {
                $query->where(function (Builder $where) use ($source, $player): void {
                    foreach ($source->players as $column) {
                        $where->orWhere($column, $player);
                    }
                });
            }
        }

        $room = (int) ($filters['room'] ?? 0);

        if ($room > 0 && $source->room !== null) {
            $query->where($source->room, $room);
        }

        $text = trim((string) ($filters['text'] ?? ''));

        if ($text !== '' && $source->search !== []) {
            $like = '%' . addcslashes($text, '\\%_') . '%';
            $query->where(function (Builder $where) use ($source, $like): void {
                foreach ($source->search as $column) {
                    $where->orWhere($column, 'like', $like);
                }
            });
        } elseif ($text !== '') {
            // A source with nothing to search can't match text.
            $query->whereRaw('1 = 0');
        }

        if (($filters['from'] ?? null) !== null) {
            $query->where($source->time, '>=', self::bound($source, (int) $filters['from']));
        }

        if (($filters['to'] ?? null) !== null) {
            $query->where($source->time, '<=', self::bound($source, (int) $filters['to']));
        }

        return $query;
    }

    /**
     * Count matching rows per source, capped, for the filter bar.
     *
     * @param  array<string, LogSource>  $sources
     * @param  array{player?: int|null, room?: int|null, text?: string|null, from?: int|null, to?: int|null}  $filters
     *
     * @return array<string, int>
     */
    public static function counts(array $sources, array $filters, int $cap = 1000): array
    {
        $out = [];

        foreach ($sources as $key => $source) {
            if (($filters['room'] ?? null) && $source->room === null) {
                $out[$key] = 0;

                continue;
            }

            $query = self::query($source, $filters)->reorder();
            $out[$key] = (int) DB::query()->fromSub($query->select('id')->limit($cap), 'capped')->count();
        }

        return $out;
    }

    /**
     * @param  array{t: int, s: int, i: int}  $cursor
     */
    private static function applyCursor(Builder $query, LogSource $source, array $cursor, int $position): void
    {
        $t = self::bound($source, $cursor['t']);

        if ($position < $cursor['s']) {
            $query->where($source->time, '<', $t);
        } elseif ($position === $cursor['s']) {
            $query->where(function (Builder $where) use ($source, $t, $cursor): void {
                $where->where($source->time, '<', $t)
                    ->orWhere(fn (Builder $same) => $same->where($source->time, '=', $t)->where('id', '<', $cursor['i']));
            });
        } else {
            $query->where($source->time, '<=', $t);
        }
    }

    /**
     * A time bound in the column's own shape, so the comparison uses its index.
     */
    private static function bound(LogSource $source, int $epoch): mixed
    {
        return match ($source->timeType) {
            'timestamp' => date('Y-m-d H:i:s', $epoch),
            'char' => (string) $epoch,
            default => $epoch,
        };
    }

    private static function epoch(LogSource $source): string
    {
        $column = '`' . str_replace('`', '', $source->time) . '`';

        return match ($source->timeType) {
            'timestamp' => "UNIX_TIMESTAMP({$column})",
            'char', 'double' => "CAST({$column} AS UNSIGNED)",
            default => $column,
        };
    }

    /**
     * @param  array{t: int, s: int, i: int}  $cursor
     */
    public static function encode(array $cursor): string
    {
        return rtrim(strtr(base64_encode($cursor['t'] . '.' . $cursor['s'] . '.' . $cursor['i']), '+/', '-_'), '=');
    }

    /**
     * @return array{t: int, s: int, i: int}|null
     */
    public static function decode(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($raw === false || preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $raw, $m) !== 1) {
            return null;
        }

        return ['t' => (int) $m[1], 's' => (int) $m[2], 'i' => (int) $m[3]];
    }
}
