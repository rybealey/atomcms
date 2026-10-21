<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Housekeeping\Logs\LogSource;
use App\Support\Housekeeping\Logs\LogSources;
use App\Support\Housekeeping\Logs\LogStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The log explorer: every log table this rank may read, as one stream with
 * one filter bar.
 */
class LogController extends Controller
{
    private const WHEN = ['1h' => 3600, '24h' => 86400, '7d' => 604800, '30d' => 2592000, 'all' => 0];

    public function index(Request $request): JsonResponse
    {
        [$sources, $filters] = $this->scope($request);
        $page = LogStream::page($sources, $filters, LogStream::decode($request->query('cursor') !== null ? (string) $request->query('cursor') : null), 50);

        return response()->json($page);
    }

    /**
     * How many rows each source holds for the filters (capped at 1,000 a
     * source), and with `meta` the source list itself.
     */
    public function counts(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($request->boolean('meta')) {
            return response()->json([
                'sources' => array_values(array_map(fn (LogSource $s): array => ['key' => $s->key, 'group' => $s->group, 'kind' => $s->kind], LogSources::readableBy($actor))),
                'groups' => LogSources::GROUPS,
                'kinds' => LogSources::KINDS,
            ]);
        }

        [$sources, $filters] = $this->scope($request);
        $counts = LogStream::counts($sources, $filters);

        return response()->json(['counts' => $counts, 'total' => array_sum($counts)]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$sources, $filters] = $this->scope($request);
        $rows = [];
        $cursor = null;

        // Up to 5,000 rows, a page at a time.
        for ($i = 0; $i < 25; $i++) {
            $page = LogStream::page($sources, $filters, $cursor, 200);

            foreach ($page['rows'] as $row) {
                $rows[] = [
                    date('Y-m-d H:i:s', (int) $row['at']),
                    (string) $row['source'],
                    (string) $row['kind'],
                    self::sentence($row['parts']),
                    (string) json_encode($row['raw']),
                ];
            }

            if ($page['next'] === null) {
                break;
            }

            $cursor = LogStream::decode($page['next']);
        }

        return self::csv('logs', ['time', 'source', 'kind', 'event', 'raw'], $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     */
    public static function sentence(array $parts): string
    {
        $words = [];

        foreach ($parts as $part) {
            $words[] = match (true) {
                isset($part['text']) => (string) $part['text'],
                isset($part['player']) => (string) ($part['player']['username'] ?? ''),
                isset($part['room']) => '(' . ($part['name'] ?? '') . ')',
                isset($part['amount']) => (string) $part['amount'],
                isset($part['quote']) => '"' . $part['quote'] . '"',
                default => '',
            };
        }

        return trim(implode(' ', array_filter($words, fn (string $w): bool => $w !== '')));
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function csv(string $name, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, $header);

            foreach ($rows as $row) {
                // A leading =, +, - or @ would run as a formula in a spreadsheet.
                fputcsv($out, array_map(fn (mixed $v): string => preg_match('/^[=+\-@]/', (string) $v) === 1 ? "'" . $v : (string) $v, $row));
            }

            fclose($out);
        }, sprintf('%s-%s.csv', $name, date('Y-m-d-His')), ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: array<string, LogSource>, 1: array{player: int|null, room: int|null, text: string|null, from: int|null}}
     */
    private function scope(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', 'string', 'max:16'],
            'player' => ['nullable', 'integer', 'min:1'],
            'room' => ['nullable', 'integer', 'min:1'],
            'when' => ['nullable', 'in:1h,24h,7d,30d,all'],
            'off' => ['nullable', 'string', 'max:600'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $sources = LogSources::readableBy($actor);
        $off = array_filter(explode(',', (string) ($validated['off'] ?? '')));
        $sources = array_diff_key($sources, array_flip($off));

        if (! empty($validated['kind'])) {
            $sources = array_filter($sources, fn (LogSource $s): bool => $s->kind === $validated['kind']);
        }

        if (! empty($validated['source'])) {
            $sources = array_intersect_key($sources, [$validated['source'] => true]);
        }

        $window = self::WHEN[$validated['when'] ?? '24h'];

        return [$sources, [
            'player' => isset($validated['player']) ? (int) $validated['player'] : null,
            'room' => isset($validated['room']) ? (int) $validated['room'] : null,
            'text' => $validated['q'] ?? null,
            'from' => $window > 0 ? time() - $window : null,
        ]];
    }
}
