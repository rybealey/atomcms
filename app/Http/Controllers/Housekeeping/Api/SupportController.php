<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\Pulse;
use App\Support\Housekeeping\RoomRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The support queue: threads players open from the phone's Support app and
 * the staff rotation that offers them out (SupportUtility). Threads live in
 * the database, not in emulator memory, so a reply or a resolve written here
 * is real - the player reads it the next time their Support app loads; there
 * is no RCON push to open it for them.
 */
class SupportController extends Controller
{
    /** SupportUtility.StaffRank: the rank that may answer support chats in game. */
    private const STAFF_RANK = 5;

    private const STATUSES = ['waiting', 'offered', 'open', 'resolved'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:waiting,offered,open,resolved,mine'],
            'category' => ['nullable', 'string', 'max:24'],
        ]);
        abort_unless(RoleplaySchema::has('rp_support_threads'), 404, 'The support app tables are not on this hotel.');
        /** @var User $actor */
        $actor = $request->user();
        $status = (string) ($validated['status'] ?? 'waiting');
        $category = (string) ($validated['category'] ?? '');

        $query = DB::table('rp_support_threads')
            ->when($status === 'mine', fn ($q) => $q->where('status', 'open')->where('staff_id', $actor->id), fn ($q) => $q->where('status', $status))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->limit(100);
        $status === 'resolved' ? $query->orderByDesc('resolved_at') : $query->orderBy('created_at');
        $threads = $query->get();

        $firsts = $threads->isNotEmpty() && RoleplaySchema::has('rp_support_messages')
            ? DB::table('rp_support_messages')
                ->whereIn('id', DB::table('rp_support_messages')->selectRaw('MIN(id)')->whereIn('thread_id', $threads->pluck('id'))->groupBy('thread_id'))
                ->pluck('body', 'thread_id')
            : collect();
        $counts = DB::table('rp_support_threads')->groupBy('status')->selectRaw('status, COUNT(*) AS n')->pluck('n', 'status');
        $refs = PlayerRefs::many($threads->pluck('player_id')->merge($threads->pluck('staff_id')));

        return response()->json([
            'threads' => $threads->map(fn (stdClass $t): array => [
                'id' => (int) $t->id,
                'player' => PlayerRefs::pick($refs, $t->player_id),
                'staff' => (int) $t->staff_id > 0 ? PlayerRefs::pick($refs, $t->staff_id) : null,
                'category' => (string) $t->category,
                'status' => (string) $t->status,
                'subject' => (string) ($firsts->get($t->id) ?? 'No message yet'),
                'offers' => (int) $t->offers,
                'created_at' => (int) $t->created_at,
                'updated_at' => (int) $t->updated_at,
                'resolved_at' => (int) $t->resolved_at,
            ])->values(),
            'counts' => array_combine(self::STATUSES, array_map(fn (string $s): int => (int) ($counts->get($s) ?? 0), self::STATUSES)) + [
                'mine' => DB::table('rp_support_threads')->where('status', 'open')->where('staff_id', $actor->id)->count(),
            ],
            'categories' => ['report', 'broken', 'appeal', 'other'],
            'rotation' => $this->rotation($actor),
            'can_answer' => (int) $actor->rank >= self::STAFF_RANK,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $thread = DB::table('rp_support_threads')->where('id', $id)->first();
        abort_if($thread === null, 404);
        $messages = RoleplaySchema::has('rp_support_messages') ? DB::table('rp_support_messages')->where('thread_id', $id)->orderBy('id')->get() : collect();
        $refs = PlayerRefs::many($messages->pluck('author_id')->merge([$thread->player_id, $thread->staff_id]));
        $player = DB::table('users')->where('id', $thread->player_id)->first(['online', 'last_room_id']);
        $roomId = $player !== null && (string) $player->online === '1' ? (int) ($player->last_room_id ?? 0) : 0;

        return response()->json([
            'id' => (int) $thread->id,
            'player' => PlayerRefs::pick($refs, $thread->player_id),
            'staff' => (int) $thread->staff_id > 0 ? PlayerRefs::pick($refs, $thread->staff_id) : null,
            'category' => (string) $thread->category,
            'status' => (string) $thread->status,
            'created_at' => (int) $thread->created_at,
            'resolved_at' => (int) $thread->resolved_at,
            'online' => $player !== null && (string) $player->online === '1',
            'room' => $roomId > 0 ? ['id' => $roomId, 'name' => RoomRefs::many([$roomId])[$roomId] ?? 'Room ' . $roomId] : null,
            'messages' => $messages->map(fn (stdClass $m): array => [
                'id' => (int) $m->id,
                'author' => PlayerRefs::pick($refs, $m->author_id),
                'staff' => (bool) $m->from_staff,
                'body' => (string) $m->body,
                'at' => (int) $m->created_at,
            ])->values(),
        ]);
    }

    /**
     * Write a staff reply the way the phone does (RpSupportSendEvent): take
     * the thread if it is still waiting or was offered to you, then append the
     * message. The player sees it the next time their Support app loads.
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:1000']]);
        $actor = $this->staff($request);
        $thread = DB::table('rp_support_threads')->where('id', $id)->first();
        abort_if($thread === null, 404);
        abort_if((string) $thread->status === 'resolved', 422, 'This thread is resolved.');
        $now = time();
        $body = trim(preg_replace('/\s+/u', ' ', (string) $data['body']) ?? '');
        abort_if($body === '', 422, 'Write a reply first.');

        DB::transaction(function () use ($id, $actor, $body, $now, $thread): void {
            if ((string) $thread->status !== 'open') {
                DB::table('rp_support_threads')
                    ->where('id', $id)
                    ->where(fn ($w) => $w->where('status', 'waiting')->orWhere(fn ($o) => $o->where('status', 'offered')->where('staff_id', $actor->id)))
                    ->update(['status' => 'open', 'staff_id' => $actor->id, 'offered_until' => 0, 'updated_at' => $now]);
            }

            DB::table('rp_support_messages')->insert(['thread_id' => $id, 'author_id' => $actor->id, 'from_staff' => 1, 'body' => mb_substr($body, 0, 1000), 'created_at' => $now]);
            DB::table('rp_support_threads')->where('id', $id)->update(['updated_at' => $now]);
        });

        $player = PlayerRefs::many([$thread->player_id])[(int) $thread->player_id]['username'] ?? '#' . $thread->player_id;
        Audit::record('support_reply', sprintf('replied to %s’s support thread', $player), (int) $thread->player_id, after: ['message' => $body], target: ['type' => 'support_thread', 'id' => $id]);
        Pulse::forget();

        return response()->json(['message' => 'Reply saved. The player sees it the next time their Support app loads.', 'path' => 'saved']);
    }

    /**
     * Close a thread (SupportUtility.Resolve): anybody on staff may close one.
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $this->staff($request);
        $thread = DB::table('rp_support_threads')->where('id', $id)->first();
        abort_if($thread === null, 404);
        abort_if((string) $thread->status === 'resolved', 422, 'This thread is already resolved.');
        $now = time();
        DB::table('rp_support_threads')->where('id', $id)->where('status', '<>', 'resolved')->update(['status' => 'resolved', 'resolved_at' => $now, 'updated_at' => $now]);
        $player = PlayerRefs::many([$thread->player_id])[(int) $thread->player_id]['username'] ?? '#' . $thread->player_id;
        Audit::record('support_resolve', sprintf('resolved %s’s support thread', $player), (int) $thread->player_id, ['status' => (string) $thread->status], ['status' => 'resolved'], target: ['type' => 'support_thread', 'id' => $id]);
        Pulse::forget();

        return response()->json(['message' => 'Thread resolved', 'path' => 'saved']);
    }

    /**
     * The rotation: who is available, when each was last offered a thread,
     * and how many offers they let lapse.
     *
     * @return list<array<string, mixed>>
     */
    private function rotation(User $actor): array
    {
        if (! RoleplaySchema::has('rp_support_rotation')) {
            return [];
        }

        $rows = DB::table('rp_support_rotation as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->orderByDesc('r.available')
            ->orderBy('r.last_offered_at')
            ->get(['r.user_id', 'r.available', 'r.last_offered_at', 'r.missed', 'u.online']);
        $open = DB::table('rp_support_threads')->where('status', 'open')->groupBy('staff_id')->selectRaw('staff_id, COUNT(*) AS n')->pluck('n', 'staff_id');
        $refs = PlayerRefs::many($rows->pluck('user_id'));

        return array_values($rows->map(function (stdClass $r) use ($refs, $open, $actor): array {
            $online = (string) $r->online === '1';
            $chats = (int) ($open->get($r->user_id) ?? 0);

            return [
                'player' => PlayerRefs::pick($refs, $r->user_id),
                'me' => (int) $r->user_id === (int) $actor->id,
                'state' => ! $online ? 'offline' : (! (bool) $r->available ? 'away' : ($chats > 0 ? 'busy' : 'available')),
                'open' => $chats,
                'last_offered_at' => (int) $r->last_offered_at,
                'missed' => (int) $r->missed,
            ];
        })->all());
    }

    private function staff(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless((int) $actor->rank >= self::STAFF_RANK, 403, sprintf('Answering support needs rank %d, the same as the Support app in game.', self::STAFF_RANK));

        return $actor;
    }
}
