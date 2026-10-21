<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use SplFileInfo;

/**
 * Phone news (rp_news_posts): the city's one news source, the phone's News
 * app (website articles were retired with the site redesign). The emulator reads the table
 * on every open (NewsUtility.GetPosts), so a change here is seen the next time
 * a player opens News. The rules mirror RpSaveNewsPostEvent: four categories,
 * one pinned story, an image from the article library, and the newsroom
 * byline (Trina) for stories not signed by their writer.
 */
class NewsController extends Controller
{
    public const CATEGORIES = ['City Hall', 'Events', 'Crime', 'Business'];

    private const BYLINE = 'Trina';

    public function index(): JsonResponse
    {
        abort_unless(RoleplaySchema::has('rp_news_posts'), 404);
        $posts = DB::table('rp_news_posts')->orderByDesc('pinned')->orderByDesc('created_at')->limit(100)->get();
        $refs = PlayerRefs::many($posts->pluck('author_id'));
        $byline = DB::table('users')->where('username', self::BYLINE)->first(['id', 'username', 'look']);

        return response()->json([
            'posts' => $posts->map(fn (object $p): array => [
                'id' => (int) $p->id,
                'title' => (string) $p->title,
                'body' => (string) $p->body,
                'category' => (string) $p->category,
                'image' => (string) $p->image,
                'pinned' => (bool) $p->pinned,
                'anonymous' => (bool) ($p->anonymous ?? false),
                'author' => PlayerRefs::pick($refs, $p->author_id),
                'created' => (int) $p->created_at,
                'updated' => (int) $p->updated_at,
            ])->values(),
            'categories' => self::CATEGORIES,
            'byline' => $byline !== null ? ['id' => (int) $byline->id, 'username' => (string) $byline->username, 'look' => (string) $byline->look] : ['id' => 0, 'username' => self::BYLINE, 'look' => ''],
            'images' => $this->images(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        /** @var User $actor */
        $actor = $request->user();
        $now = time();

        $id = DB::transaction(function () use ($data, $actor, $now): int {
            if ($data['pinned']) {
                DB::table('rp_news_posts')->where('pinned', 1)->update(['pinned' => 0]);
            }

            return (int) DB::table('rp_news_posts')->insertGetId($data + ['author_id' => $actor->id, 'created_at' => $now, 'updated_at' => $now]);
        });
        Audit::record('news', sprintf('published the story “%s”', $data['title']), target: ['type' => 'news', 'id' => $id, 'label' => $data['title']], after: $data);

        return response()->json(['id' => $id, 'message' => 'Published · players see it the next time they open News']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $before = DB::table('rp_news_posts')->where('id', $id)->first();
        abort_if($before === null, 404);
        $data = $request->has('title') ? $this->validated($request, (string) $before->category) : ['pinned' => $request->boolean('pinned') ? 1 : 0];

        DB::transaction(function () use ($data, $id): void {
            if (! empty($data['pinned'])) {
                DB::table('rp_news_posts')->where('pinned', 1)->where('id', '<>', $id)->update(['pinned' => 0]);
            }

            DB::table('rp_news_posts')->where('id', $id)->update($data + ['updated_at' => time()]);
        });
        [$old, $new] = Audit::diff((array) $before, $data);

        if ($new !== []) {
            Audit::record('news', sprintf('changed the story “%s”', $data['title'] ?? $before->title), target: ['type' => 'news', 'id' => $id, 'label' => (string) ($data['title'] ?? $before->title)], before: $old, after: $new);
        }

        return response()->json(['message' => 'Saved · players see it the next time they open News']);
    }

    public function destroy(int $id): JsonResponse
    {
        $before = DB::table('rp_news_posts')->where('id', $id)->first();
        abort_if($before === null, 404);
        DB::table('rp_news_posts')->where('id', $id)->delete();
        Audit::record('news', sprintf('deleted the story “%s”', $before->title), target: ['type' => 'news', 'id' => $id, 'label' => (string) $before->title], before: (array) $before);

        return response()->json(['message' => 'Story deleted']);
    }

    /**
     * @return array{category: string, title: string, body: string, image: string, pinned: int, anonymous: int}
     */
    private function validated(Request $request, ?string $current = null): array
    {
        // A story from before the categories were fixed keeps its own.
        $categories = $current !== null && ! in_array($current, self::CATEGORIES, true) ? [...self::CATEGORIES, $current] : self::CATEGORIES;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'category' => ['required', Rule::in($categories)],
            'image' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9_.\-]*\.(png|gif|jpe?g|webp)$/i'],
            'pinned' => ['required', 'boolean'],
            'anonymous' => ['required', 'boolean'],
        ]);
        abort_if(isset($data['image']) && str_contains((string) $data['image'], '..'), 422, 'Pick an image from the library.');

        return [
            'category' => (string) $data['category'],
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'image' => (string) ($data['image'] ?? ''),
            'pinned' => $data['pinned'] ? 1 : 0,
            'anonymous' => $data['anonymous'] ? 1 : 0,
        ];
    }

    /**
     * The article image library the phone shows pictures from.
     *
     * @return list<string>
     */
    private function images(): array
    {
        $dir = public_path('assets/images/articles');

        if (! is_dir($dir)) {
            return [];
        }

        return array_values(array_filter(array_map(fn (SplFileInfo $f): string => $f->getFilename(), File::files($dir)), fn (string $n): bool => preg_match('/^[A-Za-z0-9_.\-]+\.(png|gif|jpe?g|webp)$/i', $n) === 1));
    }
}
