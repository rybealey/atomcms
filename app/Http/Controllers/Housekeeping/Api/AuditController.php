<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff audit: every change staff made - from this panel (activity_log, with
 * before and after, the reason and what RCON said) and in game with a staff
 * tool (logs_client_staff) - newest first.
 */
class AuditController extends Controller
{
    /** Logs the website writes for staff changes, besides the panel's own. */
    private const LOGS = [Audit::LOG, 'housekeeping-settings', 'housekeeping-permissions'];

    public function __invoke(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'staff' => ['nullable', 'integer', 'min:1'],
            'event' => ['nullable', 'string', 'max:32'],
            'path' => ['nullable', 'in:live,saved,game'],
            'range' => ['nullable', 'in:24h,7d,30d,90d'],
            'before' => ['nullable', 'integer', 'min:1'],
            'csv' => ['nullable', 'boolean'],
        ]);
        $since = time() - match ($validated['range'] ?? '7d') {
            '24h' => 86400,
            '30d' => 30 * 86400,
            '90d' => 90 * 86400,
            default => 7 * 86400,
        };
        $path = $validated['path'] ?? null;
        $limit = ! empty($validated['csv']) ? 5000 : 50;
        $rows = [];

        if ($path !== 'game') {
            $query = Activity::query()
                ->whereIn('log_name', self::LOGS)
                ->where('created_at', '>=', date('Y-m-d H:i:s', $since))
                ->when(isset($validated['staff']), fn ($q) => $q->where('causer_id', (int) $validated['staff']))
                ->when(isset($validated['event']) && $validated['event'] !== '', fn ($q) => $q->where('event', $validated['event']))
                ->when(isset($validated['before']), fn ($q) => $q->where('created_at', '<', date('Y-m-d H:i:s', (int) $validated['before'])))
                ->latest('id')
                ->limit($limit);

            if ($path !== null) {
                $query->where('properties->path', $path);
            }

            foreach ($query->get() as $entry) {
                $rows[] = self::row($entry);
            }
        }

        if (($path === null || $path === 'game') && (! isset($validated['event']) || $validated['event'] === '' || $validated['event'] === 'staff_tool') && RoleplaySchema::has('logs_client_staff')) {
            $tool = DB::table('logs_client_staff')
                ->where('timestamp', '>=', $since)
                ->when(isset($validated['staff']), fn (Builder $q) => $q->where('user_id', (int) $validated['staff']))
                ->when(isset($validated['before']), fn (Builder $q) => $q->where('timestamp', '<', (int) $validated['before']))
                ->orderByDesc('timestamp')
                ->limit($limit)
                ->get();
            $refs = PlayerRefs::many($tool->pluck('user_id'));

            foreach ($tool as $t) {
                $rows[] = [
                    'id' => 'tool-' . $t->id,
                    'at' => (int) $t->timestamp,
                    'actor' => PlayerRefs::pick($refs, $t->user_id),
                    'event' => 'staff_tool',
                    'summary' => 'used a staff tool in game',
                    'reason' => null,
                    'path' => 'game',
                    'player' => null,
                    'changes' => [['field' => 'command', 'before' => null, 'after' => (string) $t->data_string]],
                    'result' => 'In game',
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => $b['at'] <=> $a['at']);
        $rows = array_slice($rows, 0, $limit);

        if (! empty($validated['csv'])) {
            return LogController::csv('staff-audit', ['time', 'staff', 'action', 'summary', 'reason', 'path', 'result'], array_map(fn (array $r): array => [
                date('Y-m-d H:i:s', $r['at']), $r['actor']['username'] ?? 'System', $r['event'], $r['summary'], $r['reason'] ?? '', $r['path'] ?? '', $r['result'] ?? '',
            ], $rows));
        }

        return response()->json([
            'rows' => $rows,
            'next' => count($rows) === $limit ? end($rows)['at'] : null,
            'by_staff' => isset($validated['before']) ? null : $this->byStaff($since),
            'events' => Activity::query()->whereIn('log_name', self::LOGS)->distinct()->orderBy('event')->pluck('event')->filter()->values(),
            'staff' => $this->staffList($since),
        ]);
    }

    /**
     * One audit entry as the panel shows it.
     *
     * @return array<string, mixed>
     */
    public static function row(Activity $entry): array
    {
        /** @var array<string, mixed> $props */
        $props = $entry->properties?->toArray() ?? [];
        $causer = $entry->causer_id !== null ? PlayerRefs::many([$entry->causer_id]) : [];
        $before = is_array($props['before'] ?? null) ? $props['before'] : [];
        $after = is_array($props['after'] ?? null) ? $props['after'] : [];

        // The website's own settings log keeps old/new under its own keys.
        if ($before === [] && $after === [] && isset($props['old'], $props['new'])) {
            $before = ['value' => $props['old']];
            $after = ['value' => $props['new']];
        }

        $changes = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $field) {
            $changes[] = ['field' => (string) $field, 'before' => self::show($before[$field] ?? null), 'after' => self::show($after[$field] ?? null)];
        }

        $rcon = is_array($props['rcon'] ?? null) ? $props['rcon'] : null;

        return [
            'id' => (string) $entry->id,
            'at' => Epoch::of($entry->created_at),
            'actor' => PlayerRefs::pick($causer, $entry->causer_id),
            'event' => (string) $entry->event,
            'summary' => lcfirst((string) $entry->description),
            'reason' => is_string($props['reason'] ?? null) ? $props['reason'] : null,
            'path' => is_string($props['path'] ?? null) ? $props['path'] : ($entry->log_name === Audit::LOG ? 'saved' : 'saved'),
            'player' => isset($props['player_id']) ? PlayerRefs::pick(PlayerRefs::many([$props['player_id']]), $props['player_id']) : null,
            'changes' => $changes,
            'result' => $rcon !== null ? ($rcon['sent'] ? 'Sent through RCON (' . $rcon['command'] . ')' : 'RCON failed: ' . $rcon['message']) : 'Saved',
        ];
    }

    private static function show(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }

    /**
     * @return list<array{player: array<string, mixed>|null, actions: int, coins: int}>
     */
    private function byStaff(int $since): array
    {
        $rows = DB::table('activity_log')
            ->whereIn('log_name', self::LOGS)
            ->where('created_at', '>=', date('Y-m-d H:i:s', $since))
            ->whereNotNull('causer_id')
            ->groupBy('causer_id')
            ->selectRaw("causer_id, COUNT(*) AS actions, SUM(CASE WHEN event = 'coins' THEN CAST(JSON_EXTRACT(properties, '$.after.coins') AS SIGNED) - CAST(JSON_EXTRACT(properties, '$.before.coins') AS SIGNED) ELSE 0 END) AS coins")
            ->orderByDesc('actions')
            ->limit(12)
            ->get();
        $refs = PlayerRefs::many($rows->pluck('causer_id'));

        return array_values($rows->map(fn (stdClass $r): array => ['player' => PlayerRefs::pick($refs, $r->causer_id), 'actions' => (int) $r->actions, 'coins' => (int) $r->coins])->all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function staffList(int $since): array
    {
        $ids = DB::table('activity_log')->whereIn('log_name', self::LOGS)->where('created_at', '>=', date('Y-m-d H:i:s', $since))->whereNotNull('causer_id')->distinct()->pluck('causer_id');
        $staff = User::query()->where('rank', '>=', (int) setting('min_staff_rank', 4))->pluck('id');
        $refs = PlayerRefs::many($ids->merge($staff));

        return array_values(array_map(fn (array $r): array => ['value' => (string) $r['id'], 'label' => $r['username']], $refs));
    }
}
