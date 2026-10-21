<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Logs\LogSource;
use App\Support\Housekeeping\Logs\LogSources;
use App\Support\Housekeeping\Logs\LogStream;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Saved views: named log explorer filters, private or shared, optionally
 * feeding Today's Needs attention queue with what they match today.
 */
class SavedViewController extends Controller
{
    private const KEYS = ['q', 'kind', 'source', 'player', 'room', 'when', 'off'];

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $views = DB::table('website_housekeeping_saved_views')
            ->where(fn ($w) => $w->where('user_id', $actor->id)->orWhere('shared', true))
            ->orderBy('name')
            ->get();
        $owners = PlayerRefs::many($views->pluck('user_id'));

        return response()->json([
            'views' => $views->map(fn (stdClass $v): array => [
                'id' => (int) $v->id,
                'name' => (string) $v->name,
                'description' => $v->description,
                'filters' => self::filters((string) $v->filters),
                'shared' => (bool) $v->shared,
                'on_today' => (bool) $v->on_today,
                'mine' => (int) $v->user_id === (int) $actor->id,
                'owner' => PlayerRefs::pick($owners, $v->user_id),
                'today' => self::matchesToday($actor, self::filters((string) $v->filters)),
            ])->values(),
            'sources' => array_keys(LogSources::readableBy($actor)),
            'kinds' => LogSources::KINDS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        /** @var User $actor */
        $actor = $request->user();
        $id = DB::table('website_housekeeping_saved_views')->insertGetId([
            'user_id' => $actor->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'filters' => json_encode($data['filters']),
            'shared' => (bool) ($data['shared'] ?? false),
            'on_today' => (bool) ($data['on_today'] ?? false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Audit::record('saved_view', sprintf('saved the log view “%s”', $data['name']), target: ['type' => 'saved_view', 'id' => $id], after: $data['filters']);

        return response()->json(['id' => $id, 'message' => 'View saved']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $view = $this->own($request, $id);
        $data = $this->validated($request);
        DB::table('website_housekeeping_saved_views')->where('id', $id)->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'filters' => json_encode($data['filters']),
            'shared' => (bool) ($data['shared'] ?? false),
            'on_today' => (bool) ($data['on_today'] ?? false),
            'updated_at' => now(),
        ]);
        Audit::record('saved_view', sprintf('changed the log view “%s”', $data['name']), target: ['type' => 'saved_view', 'id' => $id], before: self::filters((string) $view->filters), after: $data['filters']);

        return response()->json(['message' => 'View saved']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $view = $this->own($request, $id);
        DB::table('website_housekeeping_saved_views')->where('id', $id)->delete();
        Audit::record('saved_view', sprintf('deleted the log view “%s”', $view->name), target: ['type' => 'saved_view', 'id' => $id], before: self::filters((string) $view->filters));

        return response()->json(['message' => 'View deleted']);
    }

    /**
     * The views a staff member sees on Today, with their matches so far today.
     *
     * @return list<array{id: int, name: string, today: int, filters: array<string, string>}>
     */
    public static function forToday(User $actor): array
    {
        $out = [];

        foreach (DB::table('website_housekeeping_saved_views')->where('on_today', true)->where(fn ($w) => $w->where('user_id', $actor->id)->orWhere('shared', true))->limit(10)->get() as $view) {
            $filters = self::filters((string) $view->filters);
            $n = self::matchesToday($actor, $filters);

            if ($n > 0) {
                $out[] = ['id' => (int) $view->id, 'name' => (string) $view->name, 'today' => $n, 'filters' => $filters];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $filters
     */
    public static function matchesToday(User $actor, array $filters): int
    {
        return (int) Cache::remember('housekeeping:view-today:' . $actor->rank . ':' . md5((string) json_encode($filters)), now()->addMinute(), function () use ($actor, $filters): int {
            $sources = LogSources::readableBy($actor);
            $sources = array_diff_key($sources, array_flip(array_filter(explode(',', $filters['off'] ?? ''))));

            if (($filters['kind'] ?? '') !== '') {
                $sources = array_filter($sources, fn (LogSource $s): bool => $s->kind === $filters['kind']);
            }

            if (($filters['source'] ?? '') !== '') {
                $sources = array_intersect_key($sources, [$filters['source'] => true]);
            }

            return array_sum(LogStream::counts($sources, [
                'player' => ($filters['player'] ?? '') !== '' ? (int) $filters['player'] : null,
                'room' => ($filters['room'] ?? '') !== '' ? (int) $filters['room'] : null,
                'text' => $filters['q'] ?? null,
                'from' => (int) Carbon::today()->timestamp,
            ], 500));
        });
    }

    /**
     * @return array<string, string>
     */
    private static function filters(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];

        foreach (self::KEYS as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key]) && (string) $decoded[$key] !== '') {
                $out[$key] = (string) $decoded[$key];
            }
        }

        return $out;
    }

    /**
     * @return array{name: string, description?: string|null, filters: array<string, string>, shared?: bool, on_today?: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
            'filters' => ['required', 'array'],
            'filters.*' => ['nullable', 'string', 'max:600'],
            'shared' => ['nullable', 'boolean'],
            'on_today' => ['nullable', 'boolean'],
        ]);
        $data['filters'] = self::filters((string) json_encode($data['filters']));

        /** @var array{name: string, description?: string|null, filters: array<string, string>, shared?: bool, on_today?: bool} $data */
        return $data;
    }

    private function own(Request $request, int $id): stdClass
    {
        /** @var User $actor */
        $actor = $request->user();
        $view = DB::table('website_housekeeping_saved_views')->where('id', $id)->first();
        abort_if($view === null, 404);
        // Its owner, or for a shared view whoever runs the Access matrix.
        $admin = (bool) $view->shared && app(HousekeepingPermissionsService::class)->allows($actor, 'manage_housekeeping_permissions');
        abort_unless((int) $view->user_id === (int) $actor->id || $admin, 403, 'Only its owner can change this view.');

        return $view;
    }
}
