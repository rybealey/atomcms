<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Hotel;
use App\Support\Housekeeping\HotelResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The hotel's word filter (PlusEMU `wordfilter`). The emulator holds it in
 * memory, so every change is saved and then reloaded over RCON.
 *
 * How WordFilterManager reads a row: `strict` 1 replaces the entry anywhere
 * in a message, 0 only where it stands as a whole word. Entries are matched
 * against the lower-cased message, so they are stored lower case.
 */
class WordFilterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $rows = DB::table('wordfilter')
            ->when($q !== '', fn ($b) => $b->where('word', 'like', '%' . addcslashes($q, '\\%_') . '%')->orWhere('replacement', 'like', '%' . addcslashes($q, '\\%_') . '%'))
            ->orderBy('word')
            ->limit(1000)
            ->get();
        $hits = $this->hits(array_values($rows->pluck('word')->map(fn (mixed $w): string => (string) $w)->all()));

        return response()->json([
            'total' => DB::table('wordfilter')->count(),
            'items' => $rows->map(fn (object $w): array => [
                'word' => (string) $w->word,
                'replacement' => (string) $w->replacement,
                'anywhere' => (string) $w->strict === '1',
                'bannable' => (string) $w->bannable === '1',
                'by' => (string) $w->addedby,
                'hits' => $hits[(string) $w->word] ?? null,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        abort_if(DB::table('wordfilter')->where('word', $data['word'])->exists(), 422, 'That entry is already in the filter.');
        /** @var User $actor */
        $actor = $request->user();
        DB::table('wordfilter')->insert($data + ['addedby' => $actor->username]);

        return $this->reloaded('Entry added', 'added the word filter entry “' . $data['word'] . '”', [], $data);
    }

    public function update(Request $request): JsonResponse
    {
        $original = mb_strtolower(trim((string) $request->input('original', '')));
        $before = DB::table('wordfilter')->where('word', $original)->first();
        abort_if($before === null, 404, 'That entry is not in the filter any more.');
        $data = $this->validated($request);
        abort_if($data['word'] !== $original && DB::table('wordfilter')->where('word', $data['word'])->exists(), 422, 'That entry is already in the filter.');
        DB::table('wordfilter')->where('word', $original)->update($data);
        [$old, $new] = Audit::diff((array) $before, $data);

        return $this->reloaded('Entry saved', 'changed the word filter entry “' . $data['word'] . '”', $old, $new);
    }

    public function destroy(Request $request): JsonResponse
    {
        $word = mb_strtolower(trim((string) $request->input('word', '')));
        $before = DB::table('wordfilter')->where('word', $word)->first();
        abort_if($before === null, 404, 'That entry is not in the filter any more.');
        DB::table('wordfilter')->where('word', $word)->delete();

        return $this->reloaded('Entry deleted', 'deleted the word filter entry “' . $word . '”', (array) $before, []);
    }

    /**
     * @return array{word: string, replacement: string, strict: string, bannable: string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'min:2', 'max:100'],
            'replacement' => ['required', 'string', 'max:255'],
            'anywhere' => ['required', 'boolean'],
            'bannable' => ['required', 'boolean'],
        ]);

        return [
            'word' => mb_strtolower(trim((string) $data['word'])),
            'replacement' => (string) $data['replacement'],
            'strict' => $data['anywhere'] ? '1' : '0',
            'bannable' => $data['bannable'] ? '1' : '0',
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function reloaded(string $message, string $summary, array $before, array $after): JsonResponse
    {
        $reload = Hotel::reload('filter');
        Cache::forget('housekeeping:wordfilter-words');
        Cache::forget('housekeeping:wordfilter-hits');
        Audit::record('wordfilter', $summary, target: ['type' => 'wordfilter', 'id' => (string) ($after['word'] ?? $before['word'] ?? '')], before: $before, after: $after, rcon: $reload);

        return response()->json(['message' => $this->outcome($message, $reload), 'reloaded' => $reload->sent]);
    }

    private function outcome(string $message, HotelResult $reload): string
    {
        return $reload->sent ? $message . ' · the hotel reloaded the filter' : $message . ' · the hotel could not be reached, so it applies on the next reload';
    }

    /**
     * Room chat lines from the last week that contain each entry. Shared for
     * ten minutes; a LIKE over a week of chat is not free.
     *
     * @param  list<string>  $words
     *
     * @return array<string, int>
     */
    private function hits(array $words): array
    {
        if ($words === [] || ! RoleplaySchema::has('chatlogs')) {
            return [];
        }

        /** @var array<string, int> $hits */
        $hits = Cache::remember('housekeeping:wordfilter-hits', now()->addMinutes(10), function () use ($words): array {
            $since = time() - 7 * 86400;
            $out = [];

            foreach (array_slice($words, 0, 300) as $word) {
                $out[$word] = (int) DB::table('chatlogs')->where('timestamp', '>=', $since)->where('message', 'like', '%' . addcslashes($word, '\\%_') . '%')->count();
            }

            return $out;
        });

        return $hits;
    }
}
