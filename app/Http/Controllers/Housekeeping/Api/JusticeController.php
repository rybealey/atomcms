<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\GameAbilities;
use App\Support\Housekeeping\Logs\LogSources;
use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\RoomRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The justice desk: who is in a cell, open charge sheets, the 911 feed and
 * the crime catalogue. Releases and charge changes happen in the City Panel;
 * the catalogue is the one roleplay table edited here, because nothing in
 * the emulator caches it - :charge reads it on next use.
 */
class JusticeController extends Controller
{
    /** The 911 feed reaches back this far. */
    private const FEED = 7200;

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $openOnly = $request->query('charges', 'open') !== 'all';
        $crimes = RoleplaySchema::has('rp_crimes') ? DB::table('rp_crimes')->orderBy('sort_order')->orderBy('id')->get() : collect();
        $byId = $crimes->keyBy('id');

        $custody = RoleplaySchema::has('rp_jail')
            ? DB::table('rp_jail as j')->leftJoin('users as u', 'u.id', '=', 'j.user_id')->where('j.released_at', 0)->orderBy('j.seconds_left')->get(['j.*', 'u.online'])
            : collect();
        $calls = RoleplaySchema::has('rp_911_calls')
            ? DB::table('rp_911_calls')->where('created_at', '>=', time() - self::FEED)->orderByDesc('created_at')->limit(30)->get()
            : collect();
        $backup = RoleplaySchema::has('rp_911_responders') && $calls->isNotEmpty()
            ? DB::table('rp_911_responders')->whereIn('call_id', $calls->where('kind', 1)->pluck('id'))->orderBy('responded_at')->get()->groupBy('call_id')
            : collect();
        $charges = RoleplaySchema::has('rp_charges')
            ? DB::table('rp_charges')->when($openOnly, fn ($q) => $q->where('dropped_at', 0))->orderByDesc('charged_at')->limit(300)->get()
            : collect();
        $refs = PlayerRefs::many($custody->pluck('user_id')->merge($custody->pluck('officer_id'))->merge($calls->pluck('caller_id'))->merge($calls->pluck('responder_id'))->merge($charges->pluck('user_id'))->merge($charges->pluck('officer_id'))->merge($backup->flatten(1)->pluck('officer_id')));
        $rooms = RoomRefs::many($custody->pluck('room_id'));
        $openCalls = $calls->filter(fn (stdClass $c): bool => (int) $c->kind === 0 && (int) $c->responder_id === 0 && (int) $c->created_at >= time() - 3600)->count();

        $sheets = $charges->groupBy('user_id')->map(function ($rows, $userId) use ($refs, $byId): array {
            $total = 0;
            $jailOnly = false;
            $list = $rows->map(function (stdClass $c) use ($refs, $byId, &$total, &$jailOnly): array {
                $crime = $byId->get($c->crime_id);
                $ticket = $crime !== null && (int) $crime->ticketable === 1 ? (int) $crime->ticket_amount : null;
                $total += $ticket ?? 0;
                $jailOnly = $jailOnly || $ticket === null;

                return [
                    'id' => (int) $c->id,
                    'crime' => $crime !== null ? (string) $crime->name : 'Crime ' . $c->crime_id,
                    'severity' => $crime !== null ? (int) $crime->severity : 1,
                    'ticket' => $ticket,
                    'jail' => $crime !== null ? (int) $crime->jail_seconds : 0,
                    'officer' => PlayerRefs::pick($refs, $c->officer_id),
                    'at' => (int) $c->charged_at,
                    'dropped_at' => (int) $c->dropped_at,
                ];
            })->values()->all();

            return ['player' => PlayerRefs::pick($refs, $userId), 'ticket_total' => $total, 'jail_only' => $jailOnly && $total === 0, 'charges' => $list];
        })->sortByDesc(fn (array $s): int => max([0, ...array_column($s['charges'], 'severity')]))->values()->all();

        return response()->json([
            'now' => time(),
            'summary' => [
                'custody' => $custody->count(),
                'open_charges' => $charges->where('dropped_at', 0)->count(),
                'charged_players' => $charges->where('dropped_at', 0)->pluck('user_id')->unique()->count(),
                'open_calls' => $openCalls,
            ],
            'custody' => $custody->map(fn (stdClass $j): array => [
                'id' => (int) $j->id,
                'player' => PlayerRefs::pick($refs, $j->user_id),
                'officer' => PlayerRefs::pick($refs, $j->officer_id),
                'room' => ['id' => (int) $j->room_id, 'name' => $rooms[(int) $j->room_id] ?? 'Room ' . $j->room_id],
                'charges' => LogSources::chargeLines((string) $j->charges),
                'sentence' => (int) $j->sentence_seconds,
                'left' => (int) $j->seconds_left,
                'online' => (string) $j->online === '1',
                'jailed_at' => (int) $j->jailed_at,
                'trial' => (int) $j->trial_requested_at > 0 && (int) $j->trial_result === 0,
            ])->values(),
            'calls' => $calls->map(function (stdClass $c) use ($refs, $backup): array {
                $responders = (int) $c->kind === 1
                    ? $backup->get($c->id, collect())->map(fn (stdClass $r): ?array => PlayerRefs::pick($refs, $r->officer_id, (string) $r->officer_name))->filter()->values()->all()
                    : ((int) $c->responder_id > 0 ? [PlayerRefs::pick($refs, $c->responder_id, (string) $c->responder_name)] : []);

                return [
                    'id' => (int) $c->id,
                    'kind' => (int) $c->kind === 1 ? 'backup' : '911',
                    'at' => (int) $c->created_at,
                    'message' => (string) $c->message,
                    'caller' => PlayerRefs::pick($refs, $c->caller_id, (string) $c->caller_name),
                    'room' => ['id' => (int) $c->room_id, 'name' => (string) $c->room_name],
                    'responders' => $responders,
                    'response' => (int) $c->responded_at > 0 ? (int) $c->responded_at - (int) $c->created_at : null,
                    'mark' => match ((int) $c->mark) {
                        1 => 'helpful', 2 => 'abuse', default => null
                    },
                    'open' => (int) $c->kind === 0 && (int) $c->responder_id === 0,
                ];
            })->values(),
            'sheets' => $sheets,
            'crimes' => $crimes->map(fn (stdClass $c): array => self::crime($c))->values(),
            'can_edit_crimes' => GameAbilities::allows($actor, 'rp_city_justice'),
        ]);
    }

    public function storeCrime(Request $request): JsonResponse
    {
        $this->mayEdit($request);
        $data = $this->validateCrime($request);
        $data['sort_order'] = (int) (DB::table('rp_crimes')->max('sort_order') ?? 0) + 1;
        $id = DB::table('rp_crimes')->insertGetId($data);
        Cache::forget('housekeeping:crime-names');
        Audit::record('crime', sprintf('added the crime %s', $data['name']), target: ['type' => 'crime', 'id' => $id, 'label' => $data['name']], after: $data);

        return response()->json(['id' => $id, 'message' => 'Crime added'], 201);
    }

    public function updateCrime(Request $request, int $id): JsonResponse
    {
        $this->mayEdit($request);
        $before = DB::table('rp_crimes')->where('id', $id)->first();
        abort_if($before === null, 404);
        $data = $this->validateCrime($request, $id);
        DB::table('rp_crimes')->where('id', $id)->update($data);
        Cache::forget('housekeeping:crime-names');
        [$old, $new] = Audit::diff((array) $before, $data);

        if ($new !== []) {
            Audit::record('crime', sprintf('changed the crime %s', $data['name']), target: ['type' => 'crime', 'id' => $id, 'label' => $data['name']], before: $old, after: $new, reason: $request->input('reason'));
        }

        return response()->json(['message' => 'Crime saved']);
    }

    /**
     * Retire rather than delete when the crime has history: a charge row
     * points at this id, and a rap sheet that cannot name what it is for is
     * worse than a crime nobody can pick any more.
     */
    public function destroyCrime(Request $request, int $id): JsonResponse
    {
        $this->mayEdit($request);
        $crime = DB::table('rp_crimes')->where('id', $id)->first();
        abort_if($crime === null, 404);

        if (DB::table('rp_charges')->where('crime_id', $id)->exists()) {
            DB::table('rp_crimes')->where('id', $id)->update(['active' => 0]);
            Audit::record('crime', sprintf('retired the crime %s', $crime->name), target: ['type' => 'crime', 'id' => $id, 'label' => (string) $crime->name], before: ['active' => 1], after: ['active' => 0]);

            return response()->json(['retired' => true, 'message' => 'It has charges on record, so it was turned off instead']);
        }

        DB::table('rp_crimes')->where('id', $id)->delete();
        Cache::forget('housekeeping:crime-names');
        Audit::record('crime', sprintf('deleted the crime %s', $crime->name), target: ['type' => 'crime', 'id' => $id, 'label' => (string) $crime->name], before: (array) $crime);

        return response()->json(['retired' => false, 'message' => 'Crime deleted']);
    }

    /**
     * The Justice numbers on Player 360.
     *
     * @return array<string, int|null>
     */
    public static function playerSummary(int $id): array
    {
        $calls = RoleplaySchema::has('rp_911_calls');
        $jail = RoleplaySchema::has('rp_jail');
        $answered = $calls ? DB::table('rp_911_calls')->where('responder_id', $id)->where('responded_at', '>', 0)->selectRaw('COUNT(*) AS n, AVG(responded_at - created_at) AS avg')->first() : null;

        return [
            'open_charges' => RoleplaySchema::has('rp_charges') ? DB::table('rp_charges')->where('user_id', $id)->where('dropped_at', 0)->count() : 0,
            'times_jailed' => $jail ? DB::table('rp_jail')->where('user_id', $id)->count() : 0,
            'last_jailed' => $jail ? (int) DB::table('rp_jail')->where('user_id', $id)->max('jailed_at') ?: null : null,
            'arrests_30d' => $jail ? DB::table('rp_jail')->where('officer_id', $id)->where('jailed_at', '>=', time() - 30 * 86400)->count() : 0,
            'calls_made' => $calls ? DB::table('rp_911_calls')->where('caller_id', $id)->count() : 0,
            'last_call' => $calls ? (int) DB::table('rp_911_calls')->where('caller_id', $id)->max('created_at') ?: null : null,
            'calls_answered' => $answered !== null ? (int) $answered->n : 0,
            'avg_response' => $answered !== null && $answered->avg !== null ? (int) round((float) $answered->avg) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function crime(stdClass $c): array
    {
        return [
            'id' => (int) $c->id,
            'key_name' => (string) $c->key_name,
            'name' => (string) $c->name,
            'description' => (string) $c->description,
            'jail_seconds' => (int) $c->jail_seconds,
            'severity' => (int) ($c->severity ?? 1),
            'stackable' => (int) $c->stackable === 1,
            'ticketable' => (int) ($c->ticketable ?? 0) === 1,
            'ticket_amount' => (int) ($c->ticket_amount ?? 0),
            'active' => (int) $c->active === 1,
            'sort_order' => (int) $c->sort_order,
        ];
    }

    private function mayEdit(Request $request): void
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless(RoleplaySchema::has('rp_crimes'), 404);
        abort_unless(GameAbilities::allows($actor, 'rp_city_justice'), 403, 'Editing crimes needs the rank that can use the City Panel’s Justice tab (rp_city_justice).');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCrime(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:rp_crimes,key_name' . ($ignoreId !== null ? ',' . $ignoreId : '');

        $data = $request->validate([
            // lowercase letters and digits only: it is typed into chat, where a
            // space would end the argument and a capital would not match
            'key_name' => ['required', 'string', 'max:24', 'regex:/^[a-z0-9]+$/', $unique],
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
            // an hour is already a long time to sit in a cell
            'jail_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            // the wanted-star scale the HUD and RpWantedView already speak
            'severity' => ['required', 'integer', 'min:1', 'max:5'],
            'stackable' => ['required', 'boolean'],
            // whether the player may pay a fine instead of serving jail_seconds
            'ticketable' => ['required', 'boolean'],
            // only kept when the crime is ticketable, so a ticketable crime can
            // never be priced at nothing
            'ticket_amount' => ['exclude_unless:ticketable,true', 'required', 'integer', 'min:1', 'max:100000'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $data['description'] = $data['description'] ?? '';
        $data['stackable'] = $data['stackable'] ? 1 : 0;
        $data['ticketable'] = $data['ticketable'] ? 1 : 0;
        $data['ticket_amount'] = $data['ticketable'] === 1 ? (int) $data['ticket_amount'] : 0;
        $data['active'] = $data['active'] ? 1 : 0;

        if (! isset($data['sort_order'])) {
            unset($data['sort_order']);
        }

        return $data;
    }
}
