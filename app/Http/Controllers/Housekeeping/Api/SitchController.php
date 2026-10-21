<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\GameAbilities;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Sitch and photos: posts from the phone's Sitch app and camera photos.
 * Sitch reads its tables on every request (SitchUtility), so a post removed
 * here - deleted_at and deleted_by, exactly what the in-game moderator
 * delete writes - is gone from every feed at once. There is no report table
 * behind Sitch, so "Flagged" means a post that matches the word filter or
 * carries a link.
 */
class SitchController extends Controller
{
    private const VIEWS = ['flagged', 'recent', 'removed', 'photos'];

    public function __construct(private readonly HousekeepingPermissionsService $permissions) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'view' => ['nullable', 'in:flagged,recent,removed,photos'],
            'q' => ['nullable', 'string', 'max:64'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $posts = $this->permissions->allows($actor, 'moderate_sitch') && RoleplaySchema::has('rp_sitch_posts');
        $photos = $this->permissions->allows($actor, 'manage_camera_web') && RoleplaySchema::has('camera_web');
        abort_unless($posts || $photos, 403, 'This needs moderate_sitch or manage_camera_web.');
        $view = (string) ($validated['view'] ?? ($posts ? 'flagged' : 'photos'));
        $q = trim((string) ($validated['q'] ?? ''));
        $words = $this->flagWords();

        $counts = [];

        foreach (self::VIEWS as $v) {
            $counts[$v] = $v === 'photos'
                ? ($photos ? DB::table('camera_web')->where('timestamp', '>=', time() - 30 * 86400)->count() : 0)
                : ($posts ? $this->postQuery($v, $words, '')->count() : 0);
        }

        $items = [];

        if ($view === 'photos' && $photos) {
            $rows = DB::table('camera_web')
                ->when($q !== '', fn (Builder $b) => $b->where('room_name', 'like', '%' . addcslashes($q, '\\%_') . '%'))
                ->orderByDesc('timestamp')
                ->limit(60)
                ->get();
            $refs = PlayerRefs::many($rows->pluck('user_id'));

            foreach ($rows as $p) {
                $items[] = [
                    'key' => 'photo-' . $p->id,
                    'kind' => 'photo',
                    'id' => (int) $p->id,
                    'author' => PlayerRefs::pick($refs, $p->user_id),
                    'at' => (int) $p->timestamp,
                    'body' => '',
                    'photo' => ['url' => (string) $p->url, 'room' => (string) $p->room_name],
                    'visible' => (bool) $p->visible,
                    'likes' => null,
                    'replies' => null,
                    'tags' => [],
                    'flag' => null,
                    'removed' => null,
                ];
            }
        } elseif ($posts && $view !== 'photos') {
            $rows = $this->postQuery($view, $words, $q)->orderByDesc($view === 'removed' ? 'p.deleted_at' : 'p.created_at')->limit(60)->get(['p.*', 'c.url as photo_url', 'c.room_name as photo_room']);
            $ids = $rows->pluck('id');
            $likes = RoleplaySchema::has('rp_sitch_likes') && $ids->isNotEmpty() ? DB::table('rp_sitch_likes')->whereIn('post_id', $ids)->groupBy('post_id')->selectRaw('post_id, COUNT(*) AS n')->pluck('n', 'post_id') : collect();
            $replies = $ids->isNotEmpty() ? DB::table('rp_sitch_posts')->whereIn('parent_id', $ids)->where('deleted_at', 0)->groupBy('parent_id')->selectRaw('parent_id, COUNT(*) AS n')->pluck('n', 'parent_id') : collect();
            $tags = RoleplaySchema::has('rp_sitch_tags') && $ids->isNotEmpty() ? DB::table('rp_sitch_tags')->whereIn('post_id', $ids)->get()->groupBy('post_id') : collect();
            $refs = PlayerRefs::many($rows->pluck('user_id')->merge($rows->pluck('deleted_by')));

            foreach ($rows as $p) {
                $items[] = [
                    'key' => 'post-' . $p->id,
                    'kind' => (int) $p->parent_id > 0 ? 'reply' : ((int) $p->photo_id > 0 ? 'photo_post' : 'post'),
                    'id' => (int) $p->id,
                    'author' => PlayerRefs::pick($refs, $p->user_id),
                    'at' => (int) $p->created_at,
                    'body' => (string) $p->body,
                    'photo' => (int) $p->photo_id > 0 ? ['url' => (string) ($p->photo_url ?? ''), 'room' => (string) ($p->photo_room ?? '')] : null,
                    'visible' => (int) $p->deleted_at === 0,
                    'likes' => (int) ($likes->get($p->id) ?? 0),
                    'replies' => (int) ($replies->get($p->id) ?? 0),
                    'tags' => $tags->get($p->id, collect())->pluck('tag')->values()->all(),
                    'flag' => $this->flagFor((string) $p->body, $words),
                    'removed' => (int) $p->deleted_at > 0 ? ['at' => (int) $p->deleted_at, 'by' => PlayerRefs::pick($refs, $p->deleted_by)] : null,
                ];
            }
        }

        return response()->json([
            'view' => $view,
            'items' => $items,
            'counts' => $counts,
            'tags' => $posts ? $this->trendingTags() : [],
            'can' => [
                'posts' => $posts,
                'photos' => $photos,
                'moderate' => GameAbilities::allows($actor, 'rp_sitch_moderate'),
            ],
        ]);
    }

    public function hidePost(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $actor = $this->moderator($request);
        $post = DB::table('rp_sitch_posts')->where('id', $id)->first();
        abort_if($post === null, 404);
        abort_if((int) $post->deleted_at > 0, 422, 'This post is already removed.');
        DB::table('rp_sitch_posts')->where('id', $id)->where('deleted_at', 0)->update(['deleted_at' => time(), 'deleted_by' => $actor->id]);
        Audit::record('sitch_remove', sprintf('removed a Sitch post by %s', $this->name((int) $post->user_id)), (int) $post->user_id, before: ['post' => (string) $post->body], reason: $data['reason'], target: ['type' => 'sitch_post', 'id' => $id]);

        return response()->json(['message' => 'Post removed from every feed', 'path' => 'saved']);
    }

    public function restorePost(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->moderator($request);
        $post = DB::table('rp_sitch_posts')->where('id', $id)->first();
        abort_if($post === null, 404);
        abort_if((int) $post->deleted_at === 0, 422, 'This post is not removed.');
        DB::table('rp_sitch_posts')->where('id', $id)->update(['deleted_at' => 0, 'deleted_by' => 0]);
        Audit::record('sitch_restore', sprintf('restored a Sitch post by %s', $this->name((int) $post->user_id)), (int) $post->user_id, after: ['post' => (string) $post->body], reason: $data['reason'], target: ['type' => 'sitch_post', 'id' => $id]);

        return response()->json(['message' => 'Post restored', 'path' => 'saved']);
    }

    /**
     * Hide or show a camera photo on the website and in Sitch.
     */
    public function photoVisibility(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['visible' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $photo = DB::table('camera_web')->where('id', $id)->first();
        abort_if($photo === null, 404);
        DB::table('camera_web')->where('id', $id)->update(['visible' => (bool) $data['visible'] ? 1 : 0]);
        Audit::record('photo_visibility', sprintf('%s a photo by %s', $data['visible'] ? 'showed' : 'hid', $this->name((int) $photo->user_id)), (int) $photo->user_id, ['visible' => (bool) $photo->visible], ['visible' => (bool) $data['visible']], $data['reason'], target: ['type' => 'photo', 'id' => $id]);

        return response()->json(['message' => $data['visible'] ? 'Photo shown' : 'Photo hidden', 'path' => 'saved']);
    }

    /**
     * Keep a tag out of Sitch's trending list (or let it back in), as the
     * in-game :sitch command does.
     */
    public function suppressTag(Request $request): JsonResponse
    {
        $data = $request->validate(['tag' => ['required', 'string', 'max:64', 'regex:/^#?[A-Za-z][A-Za-z0-9_]{0,63}$/'], 'suppressed' => ['required', 'boolean']]);
        $actor = $this->moderator($request);
        $tag = strtolower(ltrim((string) $data['tag'], '#'));

        if ($data['suppressed']) {
            DB::table('rp_sitch_suppressed_tags')->updateOrInsert(['tag' => $tag], ['suppressed_by' => $actor->id, 'created_at' => time()]);
        } else {
            DB::table('rp_sitch_suppressed_tags')->where('tag', $tag)->delete();
        }

        Audit::record('sitch_tag', sprintf('%s #%s on Sitch', $data['suppressed'] ? 'suppressed' : 'allowed', $tag), target: ['type' => 'sitch_tag', 'id' => $tag], after: ['suppressed' => (bool) $data['suppressed']]);

        return response()->json(['message' => $data['suppressed'] ? sprintf('#%s is out of trending', $tag) : sprintf('#%s can trend again', $tag), 'path' => 'saved']);
    }

    /**
     * @param  list<string>  $words
     */
    private function postQuery(string $view, array $words, string $q): Builder
    {
        $query = DB::table('rp_sitch_posts as p')->leftJoin('camera_web as c', 'c.id', '=', 'p.photo_id');

        match ($view) {
            'removed' => $query->where('p.deleted_at', '>', 0),
            'recent' => $query->where('p.deleted_at', 0)->where('p.created_at', '>=', time() - 7 * 86400),
            default => $query->where('p.deleted_at', 0)->where(function (Builder $w) use ($words): void {
                $w->where('p.body', 'like', '%http%')->orWhere('p.body', 'like', '%www.%');

                foreach (array_slice($words, 0, 60) as $word) {
                    $w->orWhere('p.body', 'like', '%' . addcslashes($word, '\\%_') . '%');
                }
            }),
        };

        if ($q !== '') {
            $query->where('p.body', 'like', '%' . addcslashes($q, '\\%_') . '%');
        }

        return $query;
    }

    /**
     * @param  list<string>  $words
     */
    private function flagFor(string $body, array $words): ?string
    {
        $lower = strtolower($body);

        if (str_contains($lower, 'http') || str_contains($lower, 'www.')) {
            return 'Contains a link';
        }

        foreach ($words as $word) {
            if ($word !== '' && str_contains($lower, $word)) {
                return sprintf('Word filter · “%s”', $word);
            }
        }

        return null;
    }

    /**
     * @return list<array{tag: string, posts: int, suppressed: bool}>
     */
    private function trendingTags(): array
    {
        if (! RoleplaySchema::has('rp_sitch_tags')) {
            return [];
        }

        $suppressed = RoleplaySchema::has('rp_sitch_suppressed_tags') ? DB::table('rp_sitch_suppressed_tags')->pluck('tag')->flip() : collect();
        $rows = DB::table('rp_sitch_tags')->where('created_at', '>=', time() - 7 * 86400)->groupBy('tag')->selectRaw('tag, COUNT(*) AS n')->orderByDesc('n')->limit(12)->get();
        $out = array_values($rows->map(fn (stdClass $t): array => ['tag' => (string) $t->tag, 'posts' => (int) $t->n, 'suppressed' => $suppressed->has($t->tag)])->all());

        foreach ($suppressed->keys() as $tag) {
            if (! in_array((string) $tag, array_column($out, 'tag'), true)) {
                $out[] = ['tag' => (string) $tag, 'posts' => 0, 'suppressed' => true];
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function flagWords(): array
    {
        if (! RoleplaySchema::has('wordfilter')) {
            return [];
        }

        /** @var list<string> $words */
        $words = Cache::remember('housekeeping:wordfilter-words', now()->addMinutes(10), fn (): array => array_values(DB::table('wordfilter')->pluck('word')->map(fn (mixed $w): string => strtolower(trim((string) $w)))->filter()->all()));

        return $words;
    }

    private function moderator(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($this->permissions->allows($actor, 'moderate_sitch'), 403, 'This needs the moderate_sitch capability.');
        abort_unless(GameAbilities::allows($actor, 'rp_sitch_moderate'), 403, 'Your rank cannot moderate Sitch in game either (rp_sitch_moderate).');

        return $actor;
    }

    private function name(int $id): string
    {
        return PlayerRefs::many([$id])[$id]['username'] ?? '#' . $id;
    }
}
