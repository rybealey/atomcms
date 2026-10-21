<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Actions\Badge\PurgeDrawnBadge;
use App\Http\Controllers\Controller;
use App\Models\WebsiteDrawBadge;
use App\Services\Badge\BadgeImageStorage;
use App\Services\Parsers\ExternalTextsParser;
use App\Services\Roleplay\RoleplaySchema;
use App\Services\SettingsService;
use App\Support\BadgeCode;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\Pulse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Content: badges (definitions, their names in the Nitro external texts, and
 * the players' drawings waiting for approval), achievements and the client
 * texts the emulator serves.
 */
class ContentController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * All badges, the approval queue, or badges recently given from the panel.
     */
    public function badges(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tab' => ['nullable', 'in:all,queue,given'],
            'q' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $tab = (string) ($validated['tab'] ?? 'all');
        $texts = $this->badgeTexts();
        $counts = [
            'all' => RoleplaySchema::has('badge_definitions') ? DB::table('badge_definitions')->count() : 0,
            'queue' => $this->pending()->count(),
        ];

        if ($tab === 'queue') {
            $rows = $this->pending()->orderBy('created_at')->limit(100)->get();
            $refs = PlayerRefs::many($rows->pluck('user_id'));
            $history = $rows->isNotEmpty()
                ? DB::table('website_drawbadges')->whereIn('user_id', $rows->pluck('user_id'))->groupBy('user_id')->selectRaw('user_id, SUM(published = 1) AS approved, COUNT(*) AS total')->get()->keyBy('user_id')
                : collect();

            return response()->json(['counts' => $counts, 'queue' => $rows->map(fn (object $d): array => [
                'id' => (int) $d->id,
                'code' => pathinfo((string) $d->badge_path, PATHINFO_FILENAME),
                'image' => (string) $d->badge_url,
                'name' => (string) $d->badge_name,
                'description' => (string) $d->badge_desc,
                'player' => PlayerRefs::pick($refs, $d->user_id),
                'at' => Epoch::of($d->created_at),
                'earlier' => ['approved' => (int) ($history->get($d->user_id)->approved ?? 0), 'total' => max(0, (int) ($history->get($d->user_id)->total ?? 1) - 1)],
            ])->values()]);
        }

        if ($tab === 'given') {
            $rows = DB::table('activity_log')->where('log_name', Audit::LOG)->whereIn('event', ['badge', 'drawn_badge'])->orderByDesc('id')->limit(60)->get();
            $refs = PlayerRefs::many($rows->pluck('causer_id')->merge($rows->map(fn (object $r): int => (int) (json_decode((string) $r->properties, true)['player_id'] ?? 0))));

            return response()->json(['counts' => $counts, 'given' => $rows->map(function (object $r) use ($refs): array {
                /** @var array<string, mixed> $p */
                $p = json_decode((string) $r->properties, true) ?: [];
                $code = (string) ($p['after']['badge'] ?? $p['before']['badge'] ?? $p['target']['label'] ?? '');

                return [
                    'id' => (int) $r->id,
                    'at' => Epoch::of($r->created_at),
                    'code' => $code,
                    'summary' => (string) $r->description,
                    'staff' => PlayerRefs::pick($refs, $r->causer_id),
                    'player' => isset($p['player_id']) ? PlayerRefs::pick($refs, $p['player_id']) : null,
                    'reason' => $p['reason'] ?? null,
                    'path' => $p['path'] ?? 'saved',
                ];
            })->values()]);
        }

        $q = trim((string) ($validated['q'] ?? ''));
        $query = DB::table('badge_definitions')->orderBy('code');

        if ($q !== '') {
            $like = '%' . addcslashes($q, '\\%_') . '%';
            // Names live in the external texts file, so a name search maps back to codes.
            $byName = array_slice(array_keys(array_filter($texts, fn (array $t): bool => stripos($t['name'], $q) !== false)), 0, 500);
            $query->where(fn (Builder $w) => $w->where('code', 'like', $like)->when($byName !== [], fn (Builder $or) => $or->orWhereIn('code', $byName)));
        }

        $total = (clone $query)->count();
        $page = max(1, (int) ($validated['page'] ?? 1));
        $rows = $query->forPage($page, 60)->pluck('code');
        $holders = $rows->isNotEmpty() ? DB::table('user_badges')->whereIn('badge_id', $rows)->groupBy('badge_id')->selectRaw('badge_id, COUNT(*) AS n')->pluck('n', 'badge_id') : collect();

        return response()->json([
            'counts' => $counts,
            'texts_file' => $this->textsFileState(),
            'items' => $rows->map(fn (mixed $code): array => [
                'code' => (string) $code,
                'name' => $texts[(string) $code]['name'] ?? '',
                'description' => $texts[(string) $code]['description'] ?? '',
                'holders' => (int) ($holders->get($code) ?? 0),
            ])->values(),
            'total' => $total,
            'page' => $page,
            'last_page' => (int) max(1, ceil($total / 60)),
        ]);
    }

    /**
     * Create or change a badge: its definition, its name and description in
     * the Nitro external texts and, if sent, a new 40x40 image.
     */
    public function updateBadge(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:120000'],
        ]);
        $code = BadgeCode::tryNormalize($code);
        abort_if($code === null, 422, 'Badge codes are 1 to 64 letters, numbers, underscores and hyphens.');
        $before = $this->badgeTexts()[$code] ?? null;
        $created = false;

        if (RoleplaySchema::has('badge_definitions') && DB::table('badge_definitions')->where('code', $code)->doesntExist()) {
            DB::table('badge_definitions')->insert(['code' => $code, 'required_right' => '']);
            $created = true;
        }

        $image = (string) ($data['image'] ?? '');

        try {
            if ($image !== '') {
                if (preg_match('#^data:image/(gif|png|jpeg);base64,(.+)$#', $image, $m) !== 1) {
                    abort(422, 'The image must be a GIF, PNG or JPEG.');
                }

                $bytes = base64_decode($m[2], true);
                abort_if($bytes === false || strlen($bytes) > 65536, 422, 'The image must be under 64 KB.');
                app(BadgeImageStorage::class)->store($code, $bytes);
            }

            app(ExternalTextsParser::class)->updateNitroBadgeTexts($code, (string) $data['name'], (string) ($data['description'] ?? ''));
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        Audit::record(
            'badge_definition',
            sprintf('%s the badge %s', $created ? 'created' : 'changed', $code),
            target: ['type' => 'badge', 'id' => $code, 'label' => $code],
            before: $before !== null ? ['name' => $before['name'], 'description' => $before['description']] : [],
            after: ['name' => $data['name'], 'description' => $data['description'] ?? ''] + ($image !== '' ? ['image' => 'replaced'] : []),
        );

        return response()->json(['message' => $created ? 'Badge created' : 'Badge saved', 'created' => $created]);
    }

    /**
     * Approve a player's drawing - publish it, which gives the badge to them
     * and writes its texts (WebsiteDrawBadgeObserver) - or reject it, which
     * removes the drawing, its file and any texts (PurgeDrawnBadge).
     */
    public function reviewDrawnBadge(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'name' => ['exclude_unless:action,approve', 'required', 'string', 'max:24'],
            'description' => ['nullable', 'string', 'max:255'],
            'reason' => ['exclude_unless:action,reject', 'required', 'string', 'min:3', 'max:500'],
        ]);
        $badge = WebsiteDrawBadge::query()->findOrFail($id);
        $code = pathinfo((string) $badge->badge_path, PATHINFO_FILENAME);
        Pulse::forget();

        if ($data['action'] === 'reject') {
            try {
                app(PurgeDrawnBadge::class)->execute($badge);
            } catch (Throwable $exception) {
                Log::warning('Rejecting a drawn badge could not remove every trace of it.', ['id' => $id, 'exception' => $exception->getMessage()]);
            }

            $badge->delete();
            Audit::record('drawn_badge', sprintf('rejected the drawn badge %s', $code), (int) $badge->user_id, before: ['badge' => $code, 'name' => $badge->badge_name], reason: $data['reason'], target: ['type' => 'drawn_badge', 'id' => $id, 'label' => $code]);

            return response()->json(['message' => 'Drawing rejected']);
        }

        $badge->badge_name = (string) $data['name'];
        $badge->badge_desc = (string) ($data['description'] ?? '');
        $badge->published = 1;
        $badge->save();
        Audit::record('drawn_badge', sprintf('approved the drawn badge %s', $code), (int) $badge->user_id, after: ['badge' => $code, 'name' => $data['name']], target: ['type' => 'drawn_badge', 'id' => $id, 'label' => $code]);

        return response()->json(['message' => 'Approved · the player has the badge from their next login']);
    }

    /**
     * Achievements grouped by their code, with each level.
     */
    public function achievements(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $rows = DB::table('achievements')
            ->when($q !== '', fn (Builder $b) => $b->where('group_name', 'like', '%' . addcslashes($q, '\\%_') . '%'))
            ->orderBy('category')->orderBy('group_name')->orderBy('level')
            ->get();
        $holders = RoleplaySchema::has('user_achievements')
            ? DB::table('user_achievements')->groupBy('group')->selectRaw('`group` AS g, COUNT(*) AS n')->pluck('n', 'g')
            : collect();
        $texts = $this->badgeTexts();

        return response()->json([
            'items' => $rows->groupBy('group_name')->map(fn ($levels, string $group): array => [
                'code' => $group,
                'name' => $texts[$group . '1']['name'] ?? '',
                'category' => (string) ($levels->first()->category ?? ''),
                'players' => (int) ($holders->get($group) ?? 0),
                'levels' => $levels->map(fn (object $l): array => [
                    'id' => (int) $l->id,
                    'level' => (int) $l->level,
                    'progress' => (int) $l->progress_needed,
                    'pixels' => (int) $l->reward_pixels,
                    'points' => (int) $l->reward_points,
                    'badge' => $group . $l->level,
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * Save one achievement's levels; a level without an id is added.
     */
    public function updateAchievement(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'levels' => ['required', 'array', 'min:1', 'max:50'],
            'levels.*.id' => ['nullable', 'integer', 'min:1'],
            'levels.*.level' => ['required', 'integer', 'min:1', 'max:50'],
            'levels.*.progress' => ['required', 'integer', 'min:1', 'max:100000000'],
            'levels.*.pixels' => ['required', 'integer', 'min:0', 'max:1000000'],
            'levels.*.points' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
        $first = DB::table('achievements')->where('id', $id)->first();
        abort_if($first === null, 404);
        $group = (string) $first->group_name;
        $before = DB::table('achievements')->where('group_name', $group)->orderBy('level')->get()->keyBy('id');
        $changes = [];

        DB::transaction(function () use ($data, $group, $first, $before, &$changes): void {
            foreach ($data['levels'] as $level) {
                $row = ['level' => (int) $level['level'], 'progress_needed' => (int) $level['progress'], 'reward_pixels' => (int) $level['pixels'], 'reward_points' => (int) $level['points']];

                if (! empty($level['id']) && $before->has((int) $level['id'])) {
                    [$old, $new] = Audit::diff((array) $before->get((int) $level['id']), $row);

                    if ($new !== []) {
                        DB::table('achievements')->where('id', (int) $level['id'])->update($row);
                        $changes['level ' . $level['level']] = $new;
                    }
                } else {
                    DB::table('achievements')->insert($row + ['group_name' => $group, 'category' => $first->category, 'game_id' => $first->game_id]);
                    $changes['level ' . $level['level']] = ['added' => true];
                }
            }
        });

        if ($changes !== []) {
            Audit::record('achievement', sprintf('changed the achievement %s', $group), target: ['type' => 'achievement', 'id' => $group, 'label' => $group], after: $changes);
        }

        return response()->json(['message' => 'Saved · the hotel reads it after :update achievements or a restart']);
    }

    public function texts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $query = DB::table('client_external_texts')->orderBy('key');

        if ($q !== '') {
            $like = '%' . addcslashes($q, '\\%_') . '%';
            $query->where(fn (Builder $w) => $w->where('key', 'like', $like)->orWhere('value', 'like', $like));
        }

        $total = (clone $query)->count();
        $rows = $query->forPage($page, 100)->get(['id', 'key', 'value']);
        $changed = [];

        foreach (DB::table('activity_log')->where('log_name', Audit::LOG)->where('event', 'client_text')->orderByDesc('id')->limit(500)->get(['properties', 'created_at']) as $entry) {
            $target = (int) (json_decode((string) $entry->properties, true)['target']['id'] ?? 0);
            $changed[$target] ??= Epoch::of($entry->created_at);
        }

        return response()->json([
            'items' => $rows->map(fn (object $t): array => ['id' => (int) $t->id, 'key' => (string) $t->key, 'value' => (string) $t->value, 'changed' => $changed[(int) $t->id] ?? null])->values(),
            'total' => $total,
            'page' => $page,
            'last_page' => (int) max(1, ceil($total / 100)),
        ]);
    }

    public function updateText(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['value' => ['present', 'nullable', 'string', 'max:5000']]);
        $row = DB::table('client_external_texts')->where('id', $id)->first();
        abort_if($row === null, 404);
        $value = (string) ($data['value'] ?? '');

        if ($value !== (string) $row->value) {
            DB::table('client_external_texts')->where('id', $id)->update(['value' => $value]);
            Audit::record('client_text', sprintf('changed the client text %s', $row->key), target: ['type' => 'client_text', 'id' => $id, 'label' => (string) $row->key], before: ['value' => (string) $row->value], after: ['value' => $value]);
        }

        return response()->json(['message' => 'Saved · players see it after the client reloads texts']);
    }

    /**
     * code => name and description, from the Nitro external texts file the
     * client reads. Cached until the file changes.
     *
     * @return array<string, array{name: string, description: string}>
     */
    private function badgeTexts(): array
    {
        $path = (string) $this->settings->getOrDefault('nitro_external_texts_file', '');

        if ($path === '' || ! is_readable($path)) {
            return [];
        }

        /** @var array<string, array{name: string, description: string}> $texts */
        $texts = Cache::remember('housekeeping:badge-texts:' . md5($path . filemtime($path)), now()->addHour(), function () use ($path): array {
            $decoded = json_decode((string) file_get_contents($path), true);
            $out = [];

            foreach (is_array($decoded) ? $decoded : [] as $key => $value) {
                if (! is_string($key) || ! is_scalar($value)) {
                    continue;
                }

                if (str_starts_with($key, 'badge_name_')) {
                    $out[substr($key, 11)]['name'] = (string) $value;
                } elseif (str_starts_with($key, 'badge_desc_')) {
                    $out[substr($key, 11)]['description'] = (string) $value;
                }
            }

            return array_map(fn (array $t): array => ['name' => $t['name'] ?? '', 'description' => $t['description'] ?? ''], $out);
        });

        return $texts;
    }

    /**
     * The drawn-badge queue: drawings no one has published yet.
     */
    private function pending(): Builder
    {
        return DB::table('website_drawbadges')->where('published', 0);
    }

    /**
     * Whether badge names can be written: the client's external texts file.
     *
     * @return array{path: string, writable: bool}
     */
    private function textsFileState(): array
    {
        $path = (string) $this->settings->getOrDefault('nitro_external_texts_file', '');

        return ['path' => $path, 'writable' => $path !== '' && is_file($path) && is_writable($path)];
    }
}
