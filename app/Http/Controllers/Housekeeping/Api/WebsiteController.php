<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebsiteAd;
use App\Services\HousekeepingPermissionsService;
use App\Services\Roleplay\RoleplaySchema;
use App\Services\SettingsService;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Website: what the public site shows that is not news - staff teams, home
 * page ads, the items players decorate their Me page with, article tags and
 * registration beta codes. Each type needs its own capability. News lives in
 * the phone (rp_news_posts) and is edited on the Phone news screen.
 */
class WebsiteController extends Controller
{
    public const TYPES = ['teams', 'ads', 'homeitems', 'tags', 'betacodes'];

    public const PERMISSIONS = [
        'teams' => 'manage_teams',
        'ads' => 'manage_website_ads',
        'homeitems' => 'manage_home_items',
        'tags' => 'manage_article_tags',
        'betacodes' => 'manage_website_settings',
    ];

    private const TABLES = [
        'teams' => 'website_teams',
        'ads' => 'website_ads',
        'homeitems' => 'home_items',
        'tags' => 'tags',
        'betacodes' => 'website_beta_codes',
    ];

    public function __construct(
        private readonly HousekeepingPermissionsService $permissions,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $allowed = array_values(array_filter(self::TYPES, fn (string $t): bool => $this->permissions->allows($actor, self::PERMISSIONS[$t])));
        abort_if($allowed === [], 403, 'Website content needs one of manage_teams, manage_website_ads, manage_home_items, manage_article_tags or manage_website_settings.');
        $requested = (string) $request->query('type', '');
        $type = in_array($requested, $allowed, true) ? $requested : $allowed[0];
        abort_if($requested !== '' && $requested !== $type, in_array($requested, self::TYPES, true) ? 403 : 404, sprintf('This needs the %s capability.', self::PERMISSIONS[$requested] ?? 'right'));

        $counts = [];

        foreach ($allowed as $t) {
            $counts[$t] = RoleplaySchema::has(self::TABLES[$t]) ? DB::table(self::TABLES[$t])->count() : 0;
        }

        return response()->json(['types' => $allowed, 'counts' => $counts, 'type' => $type, 'items' => $this->rows($type, $request)] + $this->extra($type));
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $this->may($request, $type);

        if ($type === 'betacodes') {
            $data = $request->validate(['count' => ['nullable', 'integer', 'min:1', 'max:100'], 'code' => ['nullable', 'string', 'max:64', 'unique:website_beta_codes,code']]);
            $codes = ($data['code'] ?? '') !== '' ? [(string) $data['code']] : array_map(fn (): string => strtoupper(Str::random(10)), range(1, (int) ($data['count'] ?? 1)));
            DB::table('website_beta_codes')->insert(array_map(fn (string $c): array => ['code' => $c, 'created_at' => now(), 'updated_at' => now()], $codes));
            Audit::record('website', sprintf('created %d beta %s', count($codes), count($codes) === 1 ? 'code' : 'codes'), target: ['type' => 'betacodes', 'id' => 'new'], after: ['codes' => implode(', ', $codes)]);

            return response()->json(['message' => count($codes) === 1 ? 'Beta code created' : sprintf('%d beta codes created', count($codes)), 'codes' => $codes]);
        }

        if ($type === 'ads') {
            $data = $request->validate(['image' => ['required', 'string', 'max:3000000']]);
            $file = $this->storeImage((string) $data['image'], 'ads', '', 'ad');
            $ad = WebsiteAd::query()->create(['image' => $file]);
            Audit::record('website', 'added a website ad', target: ['type' => 'ads', 'id' => $ad->id, 'label' => $file], after: ['image' => $file]);

            return response()->json(['message' => 'Ad added', 'id' => $ad->id]);
        }

        $row = $this->validated($request, $type, null);
        $id = DB::table(self::TABLES[$type])->insertGetId($row + ['created_at' => now(), 'updated_at' => now()]);

        if ($type === 'teams') {
            $this->members($request, $id);
        }

        Audit::record('website', sprintf('added the %s %s', $this->noun($type), $row['rank_name'] ?? $row['name'] ?? '#' . $id), target: ['type' => $type, 'id' => $id], after: $row);

        return response()->json(['message' => ucfirst($this->noun($type)) . ' added', 'id' => $id]);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $this->may($request, $type);
        $before = DB::table(self::TABLES[$type])->where('id', $id)->first();
        abort_if($before === null, 404);
        abort_if(in_array($type, ['ads', 'betacodes'], true), 422, 'Ads and beta codes are added or deleted, not edited.');
        $row = $this->validated($request, $type, $id);
        DB::table(self::TABLES[$type])->where('id', $id)->update($row + ['updated_at' => now()]);
        [$old, $new] = Audit::diff((array) $before, $row);

        if ($type === 'teams') {
            [$was, $now] = $this->members($request, $id);

            if ($was !== $now) {
                $old['members'] = implode(', ', $was);
                $new['members'] = implode(', ', $now);
            }
        }

        if ($new !== []) {
            Audit::record('website', sprintf('changed the %s %s', $this->noun($type), $row['rank_name'] ?? $row['name'] ?? '#' . $id), target: ['type' => $type, 'id' => $id], before: $old, after: $new);
        }

        return response()->json(['message' => ucfirst($this->noun($type)) . ' saved']);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $this->may($request, $type);
        $before = DB::table(self::TABLES[$type])->where('id', $id)->first();
        abort_if($before === null, 404);

        match ($type) {
            // The model removes the image file as it goes.
            'ads' => WebsiteAd::query()->whereKey($id)->first()?->delete(),
            'teams' => DB::transaction(function () use ($id): void {
                User::query()->where('team_id', $id)->update(['team_id' => null]);
                DB::table('website_teams')->where('id', $id)->delete();
            }),
            'homeitems' => DB::table('user_home_items')->where('home_item_id', $id)->exists()
                ? abort(422, 'Players own this item. Turn it off instead, so their pages keep it.')
                : $this->deleteHomeItem($id, (string) $before->image),
            default => DB::table(self::TABLES[$type])->where('id', $id)->delete(),
        };

        Audit::record('website', sprintf('deleted the %s %s', $this->noun($type), $before->rank_name ?? $before->name ?? $before->code ?? '#' . $id), target: ['type' => $type, 'id' => $id], before: (array) $before);

        return response()->json(['message' => ucfirst($this->noun($type)) . ' deleted']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $type, Request $request): array
    {
        $q = trim((string) $request->query('q', ''));
        $like = '%' . addcslashes($q, '\\%_') . '%';

        return match ($type) {
            'teams' => $this->teams(),
            'ads' => array_values(WebsiteAd::query()->orderByDesc('id')->get()->map(fn (WebsiteAd $ad): array => [
                'id' => (int) $ad->id,
                'image' => (string) $ad->image,
                'url' => $ad->image_url,
                'created' => Epoch::of($ad->created_at),
            ])->all()),
            'homeitems' => array_values(DB::table('home_items as h')
                ->leftJoin('home_categories as c', 'c.id', '=', 'h.home_category_id')
                ->when($q !== '', fn ($b) => $b->where('h.name', 'like', $like))
                ->orderBy('h.type')->orderBy('h.order')->orderBy('h.id')
                ->limit(300)
                ->get(['h.*', 'c.name as category'])
                ->map(fn (object $h): array => [
                    'id' => (int) $h->id,
                    'type' => (string) $h->type,
                    'name' => (string) $h->name,
                    'image' => (string) $h->image,
                    'url' => Storage::disk('public')->url((string) $h->image),
                    'category_id' => $h->home_category_id !== null ? (int) $h->home_category_id : null,
                    'category' => $h->category,
                    'price' => (int) $h->price,
                    'currency' => (int) $h->currency_type,
                    'enabled' => (bool) $h->enabled,
                    'limit' => $h->limit !== null ? (int) $h->limit : null,
                    'sold' => (int) $h->total_bought,
                    'order' => (int) $h->order,
                ])->all()),
            'tags' => array_values(DB::table('tags')->orderBy('name')->get()->map(fn (object $t): array => [
                'id' => (int) $t->id,
                'name' => (string) $t->name,
                'color' => (string) $t->background_color,
            ])->all()),
            'betacodes' => $this->betaCodes($q),
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function teams(): array
    {
        $teams = DB::table('website_teams')->orderBy('id')->get();
        $members = RoleplaySchema::hasColumn('users', 'team_id') && $teams->isNotEmpty()
            ? DB::table('users')->whereIn('team_id', $teams->pluck('id'))->orderBy('username')->get(['id', 'username', 'look', 'team_id'])->groupBy('team_id')
            : collect();

        return array_values($teams->map(fn (object $t): array => [
            'id' => (int) $t->id,
            'name' => (string) $t->rank_name,
            'description' => (string) $t->job_description,
            'badge' => (string) $t->badge,
            'visible' => ! (bool) $t->hidden_rank,
            'color' => (string) $t->staff_color,
            'members' => array_values($members->get($t->id, collect())->map(fn (object $u): array => ['id' => (int) $u->id, 'username' => (string) $u->username, 'look' => (string) $u->look])->all()),
        ])->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function betaCodes(string $q): array
    {
        $codes = DB::table('website_beta_codes')
            ->when($q !== '', fn ($b) => $b->where('code', 'like', '%' . addcslashes($q, '\\%_') . '%'))
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        $refs = PlayerRefs::many($codes->pluck('user_id'));

        return array_values($codes->map(fn (object $c): array => [
            'id' => (int) $c->id,
            'code' => (string) $c->code,
            'player' => $c->user_id !== null ? PlayerRefs::pick($refs, $c->user_id) : null,
            'created' => Epoch::of($c->created_at),
        ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function extra(string $type): array
    {
        return match ($type) {
            'homeitems' => ['categories' => DB::table('home_categories')->orderBy('order')->get(['id', 'name'])->map(fn (object $c): array => ['value' => (string) $c->id, 'label' => (string) $c->name])->values()],
            'teams' => ['badges_path' => (string) $this->settings->getOrDefault('badges_path', '')],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $type, ?int $id): array
    {
        if ($type === 'teams') {
            $d = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],
                'badge' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]*$/'],
                'visible' => ['required', 'boolean'],
                'members' => ['nullable', 'array', 'max:200'],
                'members.*' => ['integer', 'min:1'],
            ]);

            return ['rank_name' => $d['name'], 'job_description' => $d['description'] ?? null, 'badge' => ($d['badge'] ?? '') !== '' ? $d['badge'] : null, 'hidden_rank' => $d['visible'] ? 0 : 1];
        }

        if ($type === 'tags') {
            $d = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ]);

            return ['name' => $d['name'], 'background_color' => $d['color']];
        }

        $d = $request->validate([
            'type' => ['required', 'in:s,w,n,b'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:home_categories,id'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'currency' => ['required', 'integer', 'in:-1,0,5,101'],
            'enabled' => ['required', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'image' => [$id === null ? 'required' : 'nullable', 'string', 'max:3000000'],
        ]);
        $row = [
            'type' => $d['type'],
            'name' => $d['name'],
            'home_category_id' => $d['type'] === 's' ? ($d['category_id'] ?? null) : null,
            'price' => (int) $d['price'],
            'currency_type' => (int) $d['currency'],
            'enabled' => $d['enabled'] ? 1 : 0,
            'limit' => $d['limit'] ?? null,
        ];

        if (($d['image'] ?? '') !== '') {
            $row['image'] = $this->storeImage((string) $d['image'], 'public', 'home-items', 'item');
        }

        return $row;
    }

    /**
     * Set a team's members from the request: users.team_id.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function members(Request $request, int $teamId): array
    {
        $was = array_values(DB::table('users')->where('team_id', $teamId)->orderBy('username')->pluck('username')->map(fn (mixed $n): string => (string) $n)->all());

        if (! $request->has('members')) {
            return [$was, $was];
        }

        $ids = array_map('intval', (array) $request->input('members', []));
        DB::table('users')->where('team_id', $teamId)->whereNotIn('id', $ids ?: [0])->update(['team_id' => null]);

        if ($ids !== []) {
            DB::table('users')->whereIn('id', $ids)->update(['team_id' => $teamId]);
        }

        $now = array_values(DB::table('users')->where('team_id', $teamId)->orderBy('username')->pluck('username')->map(fn (mixed $n): string => (string) $n)->all());

        return [$was, $now];
    }

    /**
     * Store a data: URL image on a disk under a fresh name, the way the old
     * upload fields did, and return the stored path.
     */
    private function storeImage(string $dataUrl, string $disk, string $directory, string $prefix): string
    {
        if (preg_match('#^data:image/(png|gif|jpeg|webp);base64,(.+)$#', $dataUrl, $m) !== 1) {
            abort(422, 'The image must be a PNG, GIF, JPEG or WebP.');
        }

        $bytes = base64_decode($m[2], true);
        abort_if($bytes === false || strlen($bytes) > 2 * 1024 * 1024, 422, 'The image must be under 2 MB.');
        abort_if(@getimagesizefromstring($bytes) === false, 422, 'That file is not an image.');
        $name = trim($directory . '/' . $prefix . '_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(6)) . '.' . ($m[1] === 'jpeg' ? 'jpg' : $m[1]), '/');
        abort_if(Storage::disk($disk)->put($name, $bytes) === false, 500, 'The image could not be saved.');

        return $name;
    }

    private function deleteHomeItem(int $id, string $image): void
    {
        DB::table('home_items')->where('id', $id)->delete();

        if ($image !== '' && Storage::disk('public')->exists($image)) {
            Storage::disk('public')->delete($image);
        }
    }

    private function may(Request $request, string $type): void
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($this->permissions->allows($actor, self::PERMISSIONS[$type]), 403, sprintf('This needs the %s capability.', self::PERMISSIONS[$type]));
    }

    private function noun(string $type): string
    {
        return match ($type) {
            'teams' => 'team',
            'ads' => 'ad',
            'homeitems' => 'home item',
            'tags' => 'tag',
            default => 'beta code',
        };
    }
}
