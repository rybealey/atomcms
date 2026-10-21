<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Account;
use App\Support\Housekeeping\ConnectionReport;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\Logs\LogSources;
use App\Support\Housekeeping\Logs\LogStream;
use App\Support\Housekeeping\PlayerColumns;
use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\RoomRefs;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Player 360's tabs past the Overview. Each reads one character, except
 * Security and Discipline, which read the whole account.
 */
class PlayerTabController extends Controller
{
    public const RANGES = ['24h' => 86400, '48h' => 172800, '7d' => 604800, '30d' => 2592000, 'all' => 0];

    public function __construct(private readonly HousekeepingPermissionsService $permissions) {}

    /**
     * Every log row for this character, newest first, by kind.
     */
    public function timeline(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['nullable', 'string', 'max:16'],
            'range' => ['nullable', 'in:24h,48h,7d,30d,all'],
            'q' => ['nullable', 'string', 'max:64'],
            'cursor' => ['nullable', 'string', 'max:64'],
        ]);
        User::query()->findOrFail($id);
        /** @var User $actor */
        $actor = $request->user();
        $range = self::RANGES[$validated['range'] ?? '48h'];
        $filters = ['player' => $id, 'text' => $validated['q'] ?? null, 'from' => $range > 0 ? time() - $range : null];
        $sources = LogSources::readableBy($actor);
        $kind = (string) ($validated['kind'] ?? 'all');
        $shown = $kind === 'all' ? $sources : array_filter($sources, fn ($s): bool => $s->kind === $kind);
        $page = LogStream::page($shown, $filters, LogStream::decode($validated['cursor'] ?? null), 40);

        $counts = null;

        if (($validated['cursor'] ?? null) === null) {
            $counts = ['all' => 0];

            foreach (LogStream::counts($sources, $filters, 500) as $key => $n) {
                $counts[$sources[$key]->kind] = ($counts[$sources[$key]->kind] ?? 0) + $n;
                $counts['all'] += $n;
            }
        }

        return response()->json($page + ['counts' => $counts, 'kinds' => LogSources::KINDS]);
    }

    /**
     * Balances, one ledger merged from the coin, bank, transfer and trade
     * tables, where money came from in the last week, diamond orders and the
     * deposit box.
     */
    public function money(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'book' => ['nullable', 'in:all,coins,bank,transfers,trades'],
            'range' => ['nullable', 'in:24h,48h,7d,30d,all'],
            'cursor' => ['nullable', 'string', 'max:64'],
        ]);
        $user = User::query()->findOrFail($id);
        /** @var User $actor */
        $actor = $request->user();
        $range = self::RANGES[$validated['range'] ?? '7d'];
        $books = [
            'coins' => ['rp_coin_ledger'],
            'bank' => ['rp_bank_transactions'],
            'transfers' => ['rp_pay_transfers'],
            'trades' => ['rp_trades'],
        ];
        $book = (string) ($validated['book'] ?? 'all');
        $keys = $book === 'all' ? array_merge(...array_values($books)) : $books[$book];
        $sources = array_intersect_key(LogSources::all(), array_flip($keys));
        $page = LogStream::page($sources, ['player' => $id, 'from' => $range > 0 ? time() - $range : null], LogStream::decode($validated['cursor'] ?? null), 30);

        if (($validated['cursor'] ?? null) !== null) {
            return response()->json(['ledger' => $page]);
        }

        $bank = RoleplaySchema::has('rp_bank_accounts') ? DB::table('rp_bank_accounts')->where('user_id', $id)->first() : null;
        $week = time() - 7 * 86400;
        $flows = $this->flows($id, $week);
        $payments = $this->permissions->allows($actor, 'view_payments');

        return response()->json([
            'balances' => [
                'coins' => (int) $user->credits,
                'diamonds' => (int) ($user->getAttribute('vip_points') ?? 0),
                'current' => $bank !== null ? (int) $bank->current_balance : null,
                'savings' => $bank !== null ? (int) $bank->savings_balance : null,
                'interest_total' => $bank !== null ? (int) $bank->interest_total : 0,
                'online' => (string) $user->online === '1',
            ],
            'net' => ['in' => $flows['in'], 'out' => $flows['out']],
            'flows' => $flows['rows'],
            'ledger' => $page,
            'payments' => $payments ? $this->payments($id) : null,
            'payments_rank' => (int) (DB::table('website_housekeeping_permissions')->where('permission', 'view_payments')->value('min_rank') ?? 0),
            'deposit_box' => $this->depositBox($id),
        ]);
    }

    /**
     * Charges against the character, their time in jail, the arrests they
     * made as an officer and their 911 calls both ways.
     */
    public function justice(Request $request, int $id): JsonResponse
    {
        User::query()->findOrFail($id);
        $crimes = LogSources::crimeNames();
        $severity = RoleplaySchema::has('rp_crimes') ? DB::table('rp_crimes')->pluck('severity', 'id') : collect();
        $jails = RoleplaySchema::has('rp_jail') ? DB::table('rp_jail')->where('user_id', $id)->orderByDesc('jailed_at')->limit(50)->get() : collect();
        $charges = RoleplaySchema::has('rp_charges') ? DB::table('rp_charges')->where('user_id', $id)->orderByDesc('charged_at')->limit(50)->get() : collect();
        $arrests = RoleplaySchema::has('rp_jail') ? DB::table('rp_jail')->where('officer_id', $id)->where('jailed_at', '>=', time() - 30 * 86400)->orderByDesc('jailed_at')->limit(50)->get() : collect();
        $made = RoleplaySchema::has('rp_911_calls') ? DB::table('rp_911_calls')->where('caller_id', $id)->orderByDesc('created_at')->limit(30)->get() : collect();
        $answered = RoleplaySchema::has('rp_911_calls')
            ? DB::table('rp_911_calls')->where('responder_id', $id)->orderByDesc('created_at')->limit(30)->get()
            : collect();
        $refs = PlayerRefs::many($charges->pluck('officer_id')->merge($jails->pluck('officer_id'))->merge($arrests->pluck('user_id'))->merge($made->pluck('responder_id'))->merge($answered->pluck('caller_id')));
        $rooms = RoomRefs::many($jails->pluck('room_id')->merge($arrests->pluck('room_id')));

        return response()->json([
            'summary' => JusticeController::playerSummary($id),
            'charges' => $charges->map(function (stdClass $c) use ($crimes, $severity, $refs, $jails): array {
                $served = (int) $c->dropped_at > 0 ? $jails->first(fn (stdClass $j): bool => abs((int) $j->jailed_at - (int) $c->dropped_at) <= 10) : null;

                return [
                    'id' => (int) $c->id,
                    'at' => (int) $c->charged_at,
                    'crime' => $crimes[(int) $c->crime_id] ?? 'Crime ' . $c->crime_id,
                    'severity' => (int) ($severity->get($c->crime_id) ?? 1),
                    'officer' => PlayerRefs::pick($refs, $c->officer_id),
                    'outcome' => match (true) {
                        (int) $c->dropped_at === 0 => ['label' => 'Open', 'tone' => 'bad'],
                        $served !== null => ['label' => 'Served ' . intdiv((int) $served->sentence_seconds + 59, 60) . 'm', 'tone' => 'warn'],
                        default => ['label' => 'Cleared', 'tone' => ''],
                    },
                ];
            })->values(),
            'jail' => $jails->map(fn (stdClass $j): array => [
                'id' => (int) $j->id,
                'at' => (int) $j->jailed_at,
                'sentence' => (int) $j->sentence_seconds,
                'served' => (int) $j->released_at > 0 ? (int) $j->sentence_seconds : (int) $j->sentence_seconds - (int) $j->seconds_left,
                'released_at' => (int) $j->released_at,
                'officer' => PlayerRefs::pick($refs, $j->officer_id),
                'room' => ['id' => (int) $j->room_id, 'name' => $rooms[(int) $j->room_id] ?? 'Room ' . $j->room_id],
                'charges' => LogSources::chargeLines((string) $j->charges),
                'trial' => match ((int) $j->trial_result) {
                    1 => 'Guilty', 2 => 'Not guilty', 3 => 'Cancelled', default => (int) $j->trial_requested_at > 0 ? 'Requested' : null
                },
            ])->values(),
            'arrests' => $arrests->map(fn (stdClass $j): array => [
                'id' => (int) $j->id,
                'at' => (int) $j->jailed_at,
                'suspect' => PlayerRefs::pick($refs, $j->user_id),
                'charges' => LogSources::chargeLines((string) $j->charges),
                'sentence' => (int) $j->sentence_seconds,
                'room' => ['id' => (int) $j->room_id, 'name' => $rooms[(int) $j->room_id] ?? 'Room ' . $j->room_id],
            ])->values(),
            'calls' => [
                'made' => $made->map(fn (stdClass $c): array => $this->call($c, $refs, 'responder'))->values(),
                'answered' => $answered->map(fn (stdClass $c): array => $this->call($c, $refs, 'caller'))->values(),
            ],
        ]);
    }

    /**
     * The conversations the character took part in within the range: rooms
     * they spoke in and people they messaged.
     */
    public function conversations(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', 'in:24h,48h,7d,30d,all'],
            'show' => ['nullable', 'in:all,room,private'],
            'q' => ['nullable', 'string', 'max:64'],
        ]);
        $user = User::query()->findOrFail($id);
        /** @var User $actor */
        $actor = $request->user();
        $range = self::RANGES[$validated['range'] ?? '48h'];
        $from = $range > 0 ? time() - $range : 0;
        $show = (string) ($validated['show'] ?? 'all');
        $like = isset($validated['q']) && $validated['q'] !== '' ? '%' . addcslashes((string) $validated['q'], '\\%_') . '%' : null;
        $rooms = [];
        $private = [];

        if ($show !== 'private' && $this->permissions->allows($actor, 'manage_room_chatlogs') && RoleplaySchema::has('chatlogs')) {
            $rows = DB::table('chatlogs')
                ->where('user_id', $id)
                ->where('timestamp', '>=', $from)
                ->when($like !== null, fn (Builder $q) => $q->where('message', 'like', $like))
                ->groupBy('room_id')
                ->selectRaw('room_id, COUNT(*) AS said, MAX(timestamp) AS last_at, MIN(timestamp) AS first_at')
                ->orderByDesc('last_at')
                ->limit(30)
                ->get();
            $names = RoomRefs::many($rows->pluck('room_id'));
            $filtered = $this->filterWords();

            foreach ($rows as $row) {
                $rooms[] = [
                    'key' => 'room-' . $row->room_id,
                    'room' => ['id' => (int) $row->room_id, 'name' => $names[(int) $row->room_id] ?? 'Room ' . $row->room_id],
                    'said' => (int) $row->said,
                    'first_at' => (int) $row->first_at,
                    'last_at' => (int) $row->last_at,
                    'filtered' => $filtered === [] ? 0 : DB::table('chatlogs')->where('user_id', $id)->where('room_id', $row->room_id)->where('timestamp', '>=', $from)
                        ->where(function (Builder $w) use ($filtered): void {
                            foreach (array_slice($filtered, 0, 40) as $word) {
                                $w->orWhere('message', 'like', '%' . addcslashes($word, '\\%_') . '%');
                            }
                        })->count(),
                ];
            }
        }

        if ($show !== 'room' && $this->permissions->allows($actor, 'manage_private_chatlogs') && RoleplaySchema::has('chatlogs_console')) {
            $rows = DB::table('chatlogs_console')
                ->where(fn (Builder $w) => $w->where('from_id', $id)->orWhere('to_id', $id))
                ->where('timestamp', '>=', $from)
                ->when($like !== null, fn (Builder $q) => $q->where('message', 'like', $like))
                ->selectRaw('IF(from_id = ?, to_id, from_id) AS other, COUNT(*) AS n, MAX(timestamp) AS last_at', [$id])
                ->groupBy('other')
                ->orderByDesc('last_at')
                ->limit(30)
                ->get();
            $refs = PlayerRefs::many($rows->pluck('other'));

            foreach ($rows as $row) {
                $private[] = [
                    'key' => 'dm-' . $row->other,
                    'with' => PlayerRefs::pick($refs, $row->other) ?? ['id' => (int) $row->other, 'username' => 'Player ' . $row->other, 'look' => ''],
                    'messages' => (int) $row->n,
                    'last_at' => (int) $row->last_at,
                ];
            }
        }

        return response()->json(['player' => ['id' => $id, 'username' => (string) $user->username], 'rooms' => $rooms, 'private' => $private]);
    }

    /**
     * One conversation's lines, both sides, newest last. A room shows everyone
     * who spoke there in the window around the character's own lines.
     */
    public function transcript(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'room' => ['nullable', 'integer', 'min:1'],
            'with' => ['nullable', 'integer', 'min:1'],
            'range' => ['nullable', 'in:24h,48h,7d,30d,all'],
            'before' => ['nullable', 'numeric'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $range = self::RANGES[$validated['range'] ?? '48h'];
        $from = $range > 0 ? time() - $range : 0;
        $before = isset($validated['before']) ? (float) $validated['before'] : null;
        $limit = 150;

        if (isset($validated['room'])) {
            abort_unless($this->permissions->allows($actor, 'manage_room_chatlogs'), 403, 'This needs the manage_room_chatlogs capability.');
            $room = (int) $validated['room'];
            $rows = DB::table('chatlogs')
                ->where('room_id', $room)
                ->where('timestamp', '>=', $from)
                ->when($before !== null, fn (Builder $q) => $q->where('timestamp', '<', $before))
                ->orderByDesc('timestamp')
                ->orderByDesc('id')
                ->limit($limit + 1)
                ->get(['id', 'user_id', 'message', 'timestamp']);
            $speakers = 'user_id';
            $source = 'chatlogs';
            $title = RoomRefs::many([$room])[$room] ?? 'Room ' . $room;
            $now = (int) DB::table('rooms')->where('id', $room)->value('users_now');
            $sub = sprintf('Room chat · room %d · %d in the room now', $room, $now);
        } else {
            abort_unless(isset($validated['with']), 422, 'Pick a room or a player.');
            abort_unless($this->permissions->allows($actor, 'manage_private_chatlogs'), 403, 'This needs the manage_private_chatlogs capability.');
            $other = (int) $validated['with'];
            $rows = DB::table('chatlogs_console')
                ->where(fn (Builder $w) => $w->where(fn (Builder $a) => $a->where('from_id', $id)->where('to_id', $other))->orWhere(fn (Builder $b) => $b->where('from_id', $other)->where('to_id', $id)))
                ->where('timestamp', '>=', $from)
                ->when($before !== null, fn (Builder $q) => $q->where('timestamp', '<', $before))
                ->orderByDesc('timestamp')
                ->orderByDesc('id')
                ->limit($limit + 1)
                ->get(['id', 'from_id as user_id', 'message', 'timestamp']);
            $speakers = 'user_id';
            $source = 'chatlogs_console';
            $title = PlayerRefs::many([$other])[$other]['username'] ?? 'Player ' . $other;
            $sub = 'Private chat · both sides shown';
        }

        $more = $rows->count() > $limit;
        $rows = $rows->take($limit)->reverse()->values();
        $refs = PlayerRefs::many($rows->pluck($speakers));
        $filtered = $this->filterWords();

        return response()->json([
            'title' => $title,
            'sub' => $sub,
            'source' => $source,
            'lines' => $rows->map(fn (stdClass $r): array => [
                'id' => (int) $r->id,
                'at' => (int) $r->timestamp,
                'player' => PlayerRefs::pick($refs, $r->user_id) ?? ['id' => (int) $r->user_id, 'username' => 'Player ' . $r->user_id, 'look' => ''],
                'text' => (string) $r->message,
                'filtered' => $this->matches((string) $r->message, $filtered),
            ])->values(),
            'before' => $more && $rows->isNotEmpty() ? (float) $rows->first()->timestamp : null,
        ]);
    }

    /**
     * Where the account plays from: guard flags, linked accounts, devices,
     * log ins and IPs. Every IP is behind edit_user.
     */
    public function security(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($this->permissions->allows($actor, 'edit_user'), 403, 'IPs and devices need the edit_user capability.');
        $rootId = Account::rootId($user);
        $root = User::query()->findOrFail($rootId);
        $characters = Account::characters($rootId);
        $linked = ConnectionReport::linked($root);
        $linkedIds = array_column($linked, 'id');
        $refs = PlayerRefs::many(array_merge($linkedIds, $characters->pluck('id')->all()));
        $reviews = RoleplaySchema::has('website_housekeeping_link_reviews')
            ? DB::table('website_housekeeping_link_reviews')->where('account_id', $rootId)->get()->keyBy('other_account_id')
            : collect();
        $guard = RoleplaySchema::has('rp_browser_guard_log')
            ? DB::table('rp_browser_guard_log')->whereIn('user_id', $characters->pluck('id'))->orderByDesc('created_at')->limit(20)->get()
            : collect();
        $connections = ConnectionReport::connections($root);
        $devices = collect($connections)->groupBy('device')->map(function (Collection $rows, string $device): array {
            $first = $rows->first();

            return [
                'device' => $device,
                'label' => trim(($first['browser'] ?? '') . ' on ' . ($first['os'] ?? ''), ' on'),
                'last_seen' => $rows->max('last_seen'),
                'accounts' => 1,
            ];
        })->values()->all();

        if ($devices !== [] && RoleplaySchema::has('website_connection_logs')) {
            $shared = DB::table('website_connection_logs')
                ->where(fn (Builder $w) => array_map(fn (array $d) => $w->orWhere('device_id', 'like', $d['device'] . '%'), $devices))
                ->selectRaw('LEFT(device_id, 8) AS d, COUNT(DISTINCT user_id) AS n')
                ->groupBy('d')
                ->pluck('n', 'd');

            foreach ($devices as &$device) {
                $device['accounts'] = (int) ($shared->get($device['device']) ?? 1);
            }

            unset($device);
        }

        $ips = collect($connections)->groupBy('ip')->map(fn (Collection $rows, string $ip): array => [
            'ip' => $ip,
            'country' => $rows->first()['country'] ?? null,
            'first_seen' => $rows->min('first_seen'),
            'last_seen' => $rows->max('last_seen'),
            'visits' => $rows->sum('visits'),
            'others' => count(array_filter($linked, fn (array $l): bool => in_array($ip, $l['ips'], true))),
        ])->sortByDesc('last_seen')->values()->all();
        $countries = collect($connections)->pluck('country', 'ip');

        return response()->json([
            'flags' => $guard->map(fn (stdClass $g): array => [
                'id' => (int) $g->id,
                'player' => PlayerRefs::pick($refs, $g->user_id, (string) $g->username),
                'reason' => LogSources::guardReason((int) $g->reason),
                'detail' => (string) $g->detail,
                'at' => (int) $g->created_at,
            ])->values(),
            'linked' => array_merge(
                $characters->filter(fn (stdClass $c): bool => (int) $c->id !== $id)->map(fn (stdClass $c): array => [
                    'player' => PlayerRefs::pick($refs, $c->id),
                    'how' => 'Same account',
                    'status' => 'same',
                    'account' => $rootId,
                ])->values()->all(),
                array_map(fn (array $l): array => [
                    'player' => PlayerRefs::pick($refs, $l['id']),
                    'how' => implode(' and ', array_filter([
                        $l['device'] ? 'same device' : null,
                        $l['hardware'] ? 'same hardware' : null,
                        $l['ips'] !== [] ? (count($l['ips']) === 1 ? 'IP ' . $l['ips'][0] : count($l['ips']) . ' shared IPs') : null,
                    ])),
                    'last_seen' => $l['last_seen'],
                    'status' => (string) ($reviews->get($l['id'])->status ?? 'flagged'),
                    'account' => $l['id'],
                ], $linked),
            ),
            'devices' => $devices,
            'logins' => array_map(fn (array $l): array => $l + ['country' => $countries->get($l['ip'])], ConnectionReport::logins($root)),
            'ips' => $ips,
        ]);
    }

    /**
     * Rooms, furni in hand, badges and the deposit box.
     */
    public function inventory(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $title = RoleplaySchema::roomTitleColumn();
        $rooms = DB::table('rooms')
            ->where('owner', $user->username)
            ->orderBy('id')
            ->get(['id', $title . ' as name', 'users_now', 'corporation_id', 'is_turf', 'rp_jail_room', 'state']);
        $placed = $rooms->isNotEmpty() ? DB::table('items')->whereIn('room_id', $rooms->pluck('id'))->groupBy('room_id')->selectRaw('room_id, COUNT(*) AS n')->pluck('n', 'room_id') : collect();
        // A furni's function is its definition's interaction type, unless that
        // one item carries an override (rp_item_function, keyed by item id).
        $inventory = DB::table('items as i')
            ->join('furniture as f', 'f.id', '=', 'i.base_item')
            ->where('i.user_id', $id)
            ->where('i.room_id', 0)
            ->groupBy('f.id', 'f.public_name', 'f.item_name', 'f.interaction_type')
            ->selectRaw('f.id, f.public_name, f.item_name, f.interaction_type, COUNT(*) AS n')
            ->orderByDesc('n')
            ->limit(60)
            ->get();
        $overrides = RoleplaySchema::has('rp_item_function') && $inventory->isNotEmpty()
            ? DB::table('rp_item_function as o')
                ->join('items as i', 'i.id', '=', 'o.item_id')
                ->where('i.user_id', $id)
                ->where('i.room_id', 0)
                ->where('o.field', 'interaction_type')
                ->groupBy('i.base_item')
                ->selectRaw('i.base_item, COUNT(*) AS n')
                ->pluck('n', 'base_item')
            : collect();
        $inHand = (int) DB::table('items')->where('user_id', $id)->where('room_id', 0)->count();
        $badges = DB::table('user_badges')->where('user_id', $id)->orderByDesc('badge_slot')->orderBy('badge_id')->get(['badge_id', 'badge_slot']);

        return response()->json([
            'rooms' => $rooms->map(fn (stdClass $r): array => [
                'id' => (int) $r->id,
                'name' => (string) $r->name,
                'now' => (int) $r->users_now,
                'placed' => (int) ($placed->get($r->id) ?? 0),
                'role' => match (true) {
                    (int) $r->corporation_id > 0 => 'HQ',
                    (string) $r->is_turf === '1' => 'Turf',
                    (string) $r->rp_jail_room === '1' => 'Jail',
                    default => 'Private',
                },
                'state' => (string) $r->state,
            ])->values(),
            'inventory' => $inventory->map(fn (stdClass $f): array => [
                'name' => (string) $f->public_name !== '' ? (string) $f->public_name : (string) $f->item_name,
                'item' => (string) $f->item_name,
                'function' => (string) $f->interaction_type !== 'default' ? (string) $f->interaction_type : null,
                'overridden' => (int) ($overrides->get($f->id) ?? 0),
                'count' => (int) $f->n,
            ])->values(),
            'in_hand' => $inHand,
            'badges' => $badges->map(fn (stdClass $b): array => ['code' => (string) $b->badge_id, 'worn' => (int) $b->badge_slot > 0])->values(),
            'deposit_box' => $this->depositBox($id),
        ]);
    }

    /**
     * Bans, mutes and notes for the whole account.
     */
    public function discipline(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $rootId = Account::rootId($user);
        $characters = Account::characters($rootId);
        $names = $characters->pluck('username')->map(fn (mixed $n): string => (string) $n)->all();
        $byName = $characters->mapWithKeys(fn (stdClass $c): array => [strtolower((string) $c->username) => (int) $c->id]);
        $history = [];

        if (PlayerColumns::isPlus()) {
            $ips = array_filter($characters->map(fn (stdClass $c): ?string => DB::table('users')->where('id', $c->id)->value(PlayerColumns::currentIp()))->all());
            $machines = array_filter($characters->map(fn (stdClass $c): ?string => RoleplaySchema::hasColumn('users', 'machine_id') ? DB::table('users')->where('id', $c->id)->value('machine_id') : null)->all());
            $bans = DB::table('bans')
                ->where(function (Builder $w) use ($names, $ips, $machines): void {
                    $w->where(fn (Builder $u) => $u->where('bantype', 'user')->whereIn('value', $names));

                    if ($ips !== []) {
                        $w->orWhere(fn (Builder $i) => $i->where('bantype', 'ip')->whereIn('value', $ips));
                    }

                    if ($machines !== []) {
                        $w->orWhere(fn (Builder $m) => $m->where('bantype', 'machine')->whereIn('value', $machines));
                    }
                })
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            foreach ($bans as $ban) {
                $history[] = [
                    'key' => 'ban-' . $ban->id,
                    'id' => (int) $ban->id,
                    'type' => 'ban',
                    'scope' => (string) $ban->bantype,
                    'at' => (int) $ban->added_date,
                    'character' => (string) $ban->bantype === 'user' ? (string) $ban->value : strtoupper((string) $ban->bantype) . ' ' . $ban->value,
                    'character_id' => $byName->get(strtolower((string) $ban->value)),
                    'staff' => (string) $ban->added_by,
                    'reason' => (string) $ban->reason,
                    'length' => max(0, (int) $ban->expire - (int) $ban->added_date),
                    'expires_at' => (int) $ban->expire,
                    'active' => (float) $ban->expire > time(),
                ];
            }
        }

        // Mutes leave no row of their own in the emulator; the panel's own are in the audit trail.
        $mutes = DB::table('activity_log')
            ->where('log_name', 'housekeeping')
            ->where('event', 'mute')
            ->where('subject_type', User::class)
            ->whereIn('subject_id', $characters->pluck('id')->map(fn (mixed $c): string => (string) $c))
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $causers = PlayerRefs::many($mutes->pluck('causer_id'));

        foreach ($mutes as $mute) {
            /** @var array<string, mixed> $props */
            $props = json_decode((string) $mute->properties, true) ?: [];
            $seconds = (int) ($props['after']['seconds'] ?? 0);
            $at = Epoch::of($mute->created_at);
            $history[] = [
                'key' => 'mute-' . $mute->id,
                'id' => (int) $mute->id,
                'type' => 'mute',
                'scope' => 'user',
                'at' => $at,
                'character' => (string) ($characters->firstWhere('id', (int) $mute->subject_id)->username ?? ''),
                'character_id' => (int) $mute->subject_id,
                'staff' => $causers[(int) $mute->causer_id]['username'] ?? 'Staff',
                'reason' => (string) ($props['reason'] ?? ''),
                'length' => $seconds,
                'expires_at' => $at + $seconds,
                'active' => (float) DB::table('users')->where('id', $mute->subject_id)->value('time_muted') > 0,
            ];
        }

        $notes = RoleplaySchema::has('website_staff_notes')
            ? DB::table('website_staff_notes')->where('account_id', $rootId)->orderByDesc('pinned')->orderByDesc('created_at')->get()
            : collect();
        $authors = PlayerRefs::many($notes->pluck('author_id'));

        usort($history, fn (array $a, array $b): int => $b['at'] <=> $a['at']);
        $roomBans = RoleplaySchema::has('room_bans')
            ? DB::table('room_bans')->whereIn('user_id', $characters->pluck('id'))->where('expire', '>', time())->get()
            : collect();
        $roomNames = RoomRefs::many($roomBans->pluck('room_id'));
        $mutedNow = $characters->mapWithKeys(fn (stdClass $c): array => [(int) $c->id => (float) DB::table('users')->where('id', $c->id)->value('time_muted')])->filter(fn (float $s): bool => $s > 0);
        $ip = PlayerColumns::isPlus() ? (string) DB::table('users')->where('id', $id)->value(PlayerColumns::currentIp()) : '';
        $machine = RoleplaySchema::hasColumn('users', 'machine_id') ? (string) DB::table('users')->where('id', $id)->value('machine_id') : '';
        /** @var User $actor */
        $actor = $request->user();
        $seeIps = $this->permissions->allows($actor, 'edit_user');

        return response()->json([
            'account' => $rootId,
            'characters' => $characters->map(fn (stdClass $c): array => ['id' => (int) $c->id, 'username' => (string) $c->username, 'online' => (string) $c->online === '1'])->values(),
            'history' => $history,
            'notes' => $notes->map(fn (stdClass $n): array => [
                'id' => (int) $n->id,
                'body' => (string) $n->body,
                'pinned' => (bool) $n->pinned,
                'author' => PlayerRefs::pick($authors, $n->author_id),
                'character' => $n->character_id !== null ? (string) ($characters->firstWhere('id', (int) $n->character_id)->username ?? '') : null,
                'at' => Epoch::of($n->created_at),
            ])->values(),
            'room_bans' => $roomBans->map(fn (stdClass $b): array => ['room' => ['id' => (int) $b->room_id, 'name' => $roomNames[(int) $b->room_id] ?? 'Room ' . $b->room_id], 'expires_at' => (int) $b->expire])->values(),
            'muted' => $mutedNow->map(fn (float $s, int $c): array => ['character_id' => $c, 'seconds_left' => (int) $s])->values(),
            'block' => [
                'ip' => $seeIps ? $ip : null,
                'machine' => $seeIps ? $machine : null,
            ],
        ]);
    }

    /**
     * Coins in and out over the window, by where they came from. Moves between
     * hand and bank are the player's own and are left out.
     *
     * @return array{in: int, out: int, rows: list<array{label: string, amount: int}>}
     */
    private function flows(int $id, int $since): array
    {
        $buckets = [];
        $add = function (string $label, int $amount) use (&$buckets): void {
            $buckets[$label] = ($buckets[$label] ?? 0) + $amount;
        };

        if (RoleplaySchema::has('rp_coin_ledger')) {
            $rows = DB::table('rp_coin_ledger')->where('user_id', $id)->where('created_at', '>=', $since)->groupBy('source')->selectRaw('source, SUM(amount) AS total')->get();

            foreach ($rows as $row) {
                $source = (string) $row->source;

                if (str_starts_with($source, 'Bank ')) {
                    continue;
                }

                $add(EconomyController::category($source), (int) $row->total);
            }
        }

        if (RoleplaySchema::has('rp_bank_transactions')) {
            $rows = DB::table('rp_bank_transactions')->where('user_id', $id)->where('created_at', '>=', $since)->whereIn('kind', ['wages', 'interest', 'transfer_in', 'transfer_out', 'pay_in', 'pay_out'])->groupBy('kind')->selectRaw('kind, SUM(amount) AS total')->get();

            foreach ($rows as $row) {
                $add(match ((string) $row->kind) {
                    'wages' => 'Wages',
                    'interest' => 'Interest',
                    default => 'Transfers',
                }, (int) $row->total);
            }
        }

        if (RoleplaySchema::has('rp_pay_transfers')) {
            $add('Transfers', (int) DB::table('rp_pay_transfers')->where('recipient_id', $id)->where('created_at', '>=', $since)->sum('amount'));
            $add('Transfers', -(int) DB::table('rp_pay_transfers')->where('sender_id', $id)->where('created_at', '>=', $since)->sum('amount'));
        }

        $in = 0;
        $out = 0;
        $rows = [];

        foreach ($buckets as $label => $amount) {
            if ($amount === 0) {
                continue;
            }

            $amount > 0 ? $in += $amount : $out += -$amount;
            $rows[] = ['label' => $label, 'amount' => $amount];
        }

        usort($rows, fn (array $a, array $b): int => [$b['amount'] > 0, abs($b['amount'])] <=> [$a['amount'] > 0, abs($a['amount'])]);

        return ['in' => $in, 'out' => $out, 'rows' => $rows];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payments(int $id): array
    {
        $out = [];

        if (RoleplaySchema::has('website_diamond_orders')) {
            foreach (DB::table('website_diamond_orders')->where('user_id', $id)->orderByDesc('created_at')->limit(20)->get() as $o) {
                $out[] = ['kind' => 'order', 'id' => (int) $o->id, 'at' => Epoch::of($o->paid_at ?? $o->created_at), 'diamonds' => (int) $o->diamonds, 'cents' => (int) $o->amount_cents, 'currency' => strtoupper((string) $o->currency), 'status' => (string) $o->status];
            }
        }

        if (RoleplaySchema::has('diamonds_store_purchases')) {
            $names = RoleplaySchema::has('diamonds_store_items') ? DB::table('diamonds_store_items')->pluck('name', 'item_key') : collect();

            foreach (DB::table('diamonds_store_purchases')->where('user_id', $id)->orderByDesc('created_at')->limit(20)->get() as $p) {
                $out[] = ['kind' => 'store', 'id' => (int) $p->id, 'at' => Epoch::of($p->created_at), 'diamonds' => -(int) $p->diamonds_paid, 'item' => (string) ($names->get($p->item_key) ?? $p->item_key)];
            }
        }

        usort($out, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return array_slice($out, 0, 20);
    }

    /**
     * @return list<array{item: string, count: int}>
     */
    private function depositBox(int $id): array
    {
        if (! RoleplaySchema::has('user_rp_deposit_box')) {
            return [];
        }

        return array_values(DB::table('user_rp_deposit_box')->where('user_id', $id)->orderBy('slot')->get(['item', 'count'])
            ->map(fn (stdClass $b): array => ['item' => ucfirst(str_replace('_', ' ', (string) $b->item)), 'count' => (int) $b->count])->all());
    }

    /**
     * @param  array<int, array{id: int, username: string, look: string}>  $refs
     *
     * @return array<string, mixed>
     */
    private function call(stdClass $c, array $refs, string $other): array
    {
        return [
            'id' => (int) $c->id,
            'at' => (int) $c->created_at,
            'message' => (int) $c->kind === 1 ? 'Backup requested' : (string) $c->message,
            'room' => ['id' => (int) $c->room_id, 'name' => (string) $c->room_name],
            'other' => $other === 'caller' ? PlayerRefs::pick($refs, $c->caller_id, (string) $c->caller_name) : PlayerRefs::pick($refs, $c->responder_id, (string) $c->responder_name),
            'response' => (int) $c->responded_at > 0 ? (int) $c->responded_at - (int) $c->created_at : null,
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

    /**
     * @param  list<string>  $words
     */
    private function matches(string $message, array $words): ?string
    {
        $lower = strtolower($message);

        foreach ($words as $word) {
            if ($word !== '' && str_contains($lower, $word)) {
                return $word;
            }
        }

        return null;
    }
}
