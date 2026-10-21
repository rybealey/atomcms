<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Moderation tickets from the in-game report button. The emulator loads open
 * and picked tickets into memory at boot and keeps their state there
 * (ModerationManager), so a status written from here would be overwritten or
 * ignored: picking up and closing stay in the in-game mod tool, and this
 * screen reads the table with the room chat around each report.
 */
class TicketController extends Controller
{
    /** Status groups as the screen shows them. */
    private const GROUPS = [
        'open' => ['open'],
        'picked' => ['picked'],
        'closed' => ['resolved', 'abusive', 'invalid', 'deleted'],
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['status' => ['nullable', 'in:open,picked,closed']]);
        abort_unless(RoleplaySchema::has('moderation_tickets'), 404);
        $group = (string) ($validated['status'] ?? 'open');
        $rows = DB::table('moderation_tickets')
            ->whereIn('status', self::GROUPS[$group])
            ->orderByDesc($group === 'closed' ? 'id' : 'score')
            ->orderByDesc('timestamp')
            ->limit(200)
            ->get();
        $refs = PlayerRefs::many($rows->pluck('sender_id')->merge($rows->pluck('reported_id'))->merge($rows->pluck('moderator_id')));
        $topics = $this->topics();
        $counts = DB::table('moderation_tickets')->groupBy('status')->selectRaw('status, COUNT(*) AS n')->pluck('n', 'status');

        return response()->json([
            'tickets' => $rows->map(fn (stdClass $t): array => $this->row($t, $refs, $topics))->values(),
            'counts' => array_map(fn (array $statuses): int => (int) array_sum(array_map(fn (string $s): int => (int) ($counts->get($s) ?? 0), $statuses)), self::GROUPS),
        ]);
    }

    /**
     * One ticket, the lines the reporter attached, and the room chat in the
     * five minutes either side of the report.
     */
    public function show(int $id): JsonResponse
    {
        $t = DB::table('moderation_tickets')->where('id', $id)->first();
        abort_if(! $t instanceof stdClass, 404);
        /** @var Collection<int, stdClass> $chat */
        $chat = collect();

        if (RoleplaySchema::has('chatlogs') && (int) $t->room_id > 0) {
            $at = (int) $t->timestamp;
            $chat = DB::table('chatlogs')
                ->where('room_id', $t->room_id)
                ->whereBetween('timestamp', [$at - 300, $at + 300])
                ->orderBy('timestamp')
                ->orderBy('id')
                ->limit(40)
                ->get(['id', 'user_id', 'message', 'timestamp']);
        }

        $refs = PlayerRefs::many($chat->pluck('user_id')->merge([$t->sender_id, $t->reported_id, $t->moderator_id]));
        $attached = json_decode((string) $t->reported_chats, true);

        return response()->json($this->row($t, $refs, $this->topics()) + [
            'attached' => is_array($attached) ? array_values(array_filter(array_map(fn (mixed $line): string => is_scalar($line) ? (string) $line : '', $attached))) : [],
            'chat' => $chat->map(fn (stdClass $c): array => [
                'id' => (int) $c->id,
                'at' => (int) $c->timestamp,
                'player' => PlayerRefs::pick($refs, $c->user_id),
                'text' => (string) $c->message,
                'reported' => (int) $c->user_id === (int) $t->reported_id,
            ])->values(),
        ]);
    }

    /**
     * @param  array<int, array{id: int, username: string, look: string}>  $refs
     * @param  Collection<int|string, string>  $topics
     *
     * @return array<string, mixed>
     */
    private function row(stdClass $t, array $refs, Collection $topics): array
    {
        $group = 'closed';

        foreach (self::GROUPS as $name => $statuses) {
            if (in_array((string) $t->status, $statuses, true)) {
                $group = $name;
            }
        }

        return [
            'id' => (int) $t->id,
            'status' => (string) $t->status,
            'group' => $group,
            'score' => (int) $t->score,
            'category' => $topics->get($t->category) ?? sprintf('Category %d', (int) $t->category),
            'reporter' => PlayerRefs::pick($refs, $t->sender_id),
            'reported' => PlayerRefs::pick($refs, $t->reported_id),
            'moderator' => (int) $t->moderator_id > 0 ? PlayerRefs::pick($refs, $t->moderator_id) : null,
            'message' => (string) $t->message,
            'room' => (int) $t->room_id > 0 ? ['id' => (int) $t->room_id, 'name' => (string) $t->room_name] : null,
            'at' => (int) $t->timestamp,
        ];
    }

    /**
     * The report categories the client offers (moderation_topic_actions,
     * under moderation_topics), in words.
     *
     * @return Collection<int|string, string>
     */
    private function topics(): Collection
    {
        $words = fn (mixed $caption): string => ucfirst(str_replace('_', ' ', (string) $caption));

        if (RoleplaySchema::has('moderation_topic_actions')) {
            return DB::table('moderation_topic_actions')->pluck('caption', 'id')->map($words);
        }

        return RoleplaySchema::has('moderation_topics') ? DB::table('moderation_topics')->pluck('caption', 'id')->map($words) : collect();
    }
}
