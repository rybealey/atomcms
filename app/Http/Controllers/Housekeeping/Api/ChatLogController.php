<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\RoomRefs;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Chat logs: room chat (chatlogs) and private chat (chatlogs_console) in one
 * search, each hit with the five lines either side.
 */
class ChatLogController extends Controller
{
    private const WHEN = ['1h' => 3600, '24h' => 86400, '7d' => 604800, '30d' => 2592000, 'all' => 0];

    public function __construct(private readonly HousekeepingPermissionsService $permissions) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:all,room,private'],
            'player' => ['nullable', 'integer', 'min:1'],
            'room' => ['nullable', 'integer', 'min:1'],
            'when' => ['nullable', 'in:1h,24h,7d,30d,all'],
            'filtered' => ['nullable', 'boolean'],
            'context' => ['nullable', 'string', 'regex:/^(room|private):\d+$/'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $rooms = $this->permissions->allows($actor, 'manage_room_chatlogs') && RoleplaySchema::has('chatlogs');
        $private = $this->permissions->allows($actor, 'manage_private_chatlogs') && RoleplaySchema::has('chatlogs_console');
        abort_unless($rooms || $private, 403, 'Chat logs need manage_room_chatlogs or manage_private_chatlogs.');

        if (isset($validated['context'])) {
            [$kind, $id] = explode(':', (string) $validated['context']);
            abort_unless($kind === 'room' ? $rooms : $private, 403);

            return response()->json($this->context($kind, (int) $id));
        }

        $type = (string) ($validated['type'] ?? 'all');
        $window = self::WHEN[$validated['when'] ?? '24h'];
        $from = $window > 0 ? time() - $window : 0;
        $q = trim((string) ($validated['q'] ?? ''));
        $words = ! empty($validated['filtered']) ? $this->filterWords() : [];
        $limit = 100;
        $hits = [];
        $match = function (Builder $query) use ($q, $words): void {
            if ($q !== '') {
                $query->where('message', 'like', '%' . addcslashes($q, '\\%_') . '%');
            }

            if ($words !== []) {
                $query->where(function (Builder $w) use ($words): void {
                    foreach (array_slice($words, 0, 50) as $word) {
                        $w->orWhere('message', 'like', '%' . addcslashes($word, '\\%_') . '%');
                    }
                });
            }
        };

        if ($rooms && $type !== 'private') {
            $query = DB::table('chatlogs')->where('timestamp', '>=', $from)->orderByDesc('timestamp')->limit($limit);
            $match($query);

            if (isset($validated['player'])) {
                $query->where('user_id', (int) $validated['player']);
            }

            if (isset($validated['room'])) {
                $query->where('room_id', (int) $validated['room']);
            }

            foreach ($query->get(['id', 'user_id', 'room_id', 'message', 'timestamp']) as $r) {
                $hits[] = ['key' => 'room:' . $r->id, 'kind' => 'room', 'at' => (int) $r->timestamp, 'speaker' => (int) $r->user_id, 'other' => null, 'room' => (int) $r->room_id, 'text' => (string) $r->message];
            }
        }

        if ($private && $type !== 'room' && ! isset($validated['room'])) {
            $query = DB::table('chatlogs_console')->where('timestamp', '>=', $from)->orderByDesc('timestamp')->limit($limit);
            $match($query);

            if (isset($validated['player'])) {
                $player = (int) $validated['player'];
                $query->where(fn (Builder $w) => $w->where('from_id', $player)->orWhere('to_id', $player));
            }

            foreach ($query->get(['id', 'from_id', 'to_id', 'message', 'timestamp']) as $r) {
                $hits[] = ['key' => 'private:' . $r->id, 'kind' => 'private', 'at' => (int) $r->timestamp, 'speaker' => (int) $r->from_id, 'other' => (int) $r->to_id, 'room' => null, 'text' => (string) $r->message];
            }
        }

        usort($hits, fn (array $a, array $b): int => $b['at'] <=> $a['at']);
        $hits = array_slice($hits, 0, $limit);
        $refs = PlayerRefs::many(array_merge(array_column($hits, 'speaker'), array_column($hits, 'other')));
        $roomNames = RoomRefs::many(array_column($hits, 'room'));

        return response()->json([
            'hits' => array_map(fn (array $h): array => [
                'key' => $h['key'],
                'kind' => $h['kind'],
                'at' => $h['at'],
                'speaker' => PlayerRefs::pick($refs, $h['speaker']) ?? ['id' => $h['speaker'], 'username' => 'Player ' . $h['speaker'], 'look' => ''],
                'other' => $h['other'] !== null ? PlayerRefs::pick($refs, $h['other']) : null,
                'room' => $h['room'] !== null ? ['id' => $h['room'], 'name' => $roomNames[$h['room']] ?? 'Room ' . $h['room']] : null,
                'text' => $h['text'],
            ], $hits),
            'capped' => count($hits) >= $limit,
            'can' => ['room' => $rooms, 'private' => $private],
        ]);
    }

    /**
     * The five lines before and after one line, in the same room or the same
     * private conversation.
     *
     * @return array<string, mixed>
     */
    private function context(string $kind, int $id): array
    {
        if ($kind === 'room') {
            $line = DB::table('chatlogs')->where('id', $id)->first();
            abort_if($line === null, 404);
            $before = DB::table('chatlogs')->where('room_id', $line->room_id)->where('id', '<', $id)->orderByDesc('id')->limit(5)->get();
            $after = DB::table('chatlogs')->where('room_id', $line->room_id)->where('id', '>', $id)->orderBy('id')->limit(5)->get();
            $rows = $before->reverse()->push($line)->merge($after);
            $refs = PlayerRefs::many($rows->pluck('user_id'));
            $name = RoomRefs::many([$line->room_id])[(int) $line->room_id] ?? 'Room ' . $line->room_id;

            return ['title' => $name, 'source' => 'chatlogs', 'room' => (int) $line->room_id, 'lines' => $rows->map(fn (stdClass $r): array => ['id' => (int) $r->id, 'at' => (int) $r->timestamp, 'player' => PlayerRefs::pick($refs, $r->user_id), 'text' => (string) $r->message, 'hit' => (int) $r->id === $id])->values()];
        }

        $line = DB::table('chatlogs_console')->where('id', $id)->first();
        abort_if($line === null, 404);
        $pair = fn (Builder $w) => $w->where(fn (Builder $a) => $a->where('from_id', $line->from_id)->where('to_id', $line->to_id))->orWhere(fn (Builder $b) => $b->where('from_id', $line->to_id)->where('to_id', $line->from_id));
        $before = DB::table('chatlogs_console')->where($pair)->where('id', '<', $id)->orderByDesc('id')->limit(5)->get();
        $after = DB::table('chatlogs_console')->where($pair)->where('id', '>', $id)->orderBy('id')->limit(5)->get();
        $rows = $before->reverse()->push($line)->merge($after);
        $refs = PlayerRefs::many([$line->from_id, $line->to_id]);

        return [
            'title' => sprintf('Private · %s to %s', $refs[(int) $line->from_id]['username'] ?? $line->from_id, $refs[(int) $line->to_id]['username'] ?? $line->to_id),
            'source' => 'chatlogs_console',
            'room' => null,
            'lines' => $rows->map(fn (stdClass $r): array => ['id' => (int) $r->id, 'at' => (int) $r->timestamp, 'player' => PlayerRefs::pick($refs, $r->from_id), 'text' => (string) $r->message, 'hit' => (int) $r->id === $id])->values(),
        ];
    }

    /**
     * @return list<string>
     */
    private function filterWords(): array
    {
        if (! RoleplaySchema::has('wordfilter')) {
            return [];
        }

        /** @var list<string> $words */
        $words = Cache::remember('housekeeping:wordfilter-words', now()->addMinutes(10), fn (): array => array_values(DB::table('wordfilter')->pluck('word')->map(fn (mixed $w): string => strtolower(trim((string) $w)))->filter()->all()));

        return $words;
    }
}
