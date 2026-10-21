<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Account;
use App\Support\Housekeeping\GameAbilities;
use App\Support\Housekeeping\Logs\LogSources;
use App\Support\Housekeeping\Logs\LogStream;
use App\Support\Housekeeping\PlayerColumns;
use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\RankDirectory;
use App\Support\Housekeeping\RoomRefs;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * People: the players directory and Player 360's header and overview. Every
 * character is a row; the account it belongs to rides along.
 */
class PlayerController extends Controller
{
    public function __construct(
        private readonly HousekeepingPermissionsService $permissions,
        private readonly RankDirectory $ranks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'in:online,offline'],
            'rank' => ['nullable', 'integer', 'min:0'],
            'corp' => ['nullable', 'integer', 'min:0'],
            'gang' => ['nullable', 'integer', 'min:0'],
            'flag' => ['nullable', 'in:jailed,banned,vip,staff,flagged,muted'],
            'region' => ['nullable', 'string', 'max:2'],
            'created' => ['nullable', 'in:1,7,30,365'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $canSeeIps = $this->permissions->allows($actor, 'edit_user');
        $query = DB::table('users as u');
        $this->filter($query, $validated, $canSeeIps);

        $total = (clone $query)->count();
        $perPage = (int) ($validated['per_page'] ?? 25);
        $page = max(1, min((int) ($validated['page'] ?? 1), (int) max(1, ceil($total / $perPage))));

        $rows = $query
            ->select(['u.id', 'u.username', 'u.look', 'u.rank', 'u.online', 'u.last_online', 'u.account_created', 'u.credits'])
            ->addSelect(RoleplaySchema::hasColumn('users', 'parent_id') ? 'u.parent_id' : DB::raw('NULL AS parent_id'))
            ->orderByDesc('u.online')
            ->orderByDesc('u.last_online')
            ->orderByDesc('u.id')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'items' => $this->rows($rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) max(1, ceil($total / $perPage)),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * The choices the directory's filter bar offers.
     */
    public function filters(): JsonResponse
    {
        return response()->json(Cache::remember('housekeeping:player-filters', now()->addMinutes(5), fn (): array => [
            'ranks' => $this->ranks->all()->map(fn (array $r): array => ['value' => (string) $r['id'], 'label' => $r['name']])->values()->all(),
            'corporations' => RoleplaySchema::has('rp_corporations')
                ? DB::table('rp_corporations')->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->map(fn (stdClass $c): array => ['value' => (string) $c->id, 'label' => (string) $c->name])->all()
                : [],
            'gangs' => RoleplaySchema::hasColumn('groups', 'is_gang')
                ? DB::table('groups')->where('is_gang', '1')->orderBy('name')->get(['id', 'name'])->map(fn (stdClass $g): array => ['value' => (string) $g->id, 'label' => (string) $g->name])->all()
                : [],
            'regions' => RoleplaySchema::hasColumn('users', 'rp_region')
                ? DB::table('users')->whereNotNull('rp_region')->where('rp_region', '<>', '')->distinct()->orderBy('rp_region')->pluck('rp_region')->map(fn (mixed $r): array => ['value' => (string) $r, 'label' => strtoupper((string) $r)])->all()
                : [],
        ]));
    }

    /**
     * Player 360: the header and the Overview tab.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = User::query()->findOrFail($id);
        $rootId = Account::rootId($user);
        $characters = Account::characters($rootId);
        $online = (string) $user->online === '1';
        $roomId = $online && RoleplaySchema::hasColumn('users', 'last_room_id') ? (int) $user->getAttribute('last_room_id') : 0;
        $job = $this->job($id);
        $banned = $this->activeBan($rootId);
        $muted = (float) ($user->getAttribute('time_muted') ?? 0) > 0;
        $jail = RoleplaySchema::has('rp_jail') ? DB::table('rp_jail')->where('user_id', $id)->where('released_at', 0)->first(['seconds_left', 'sentence_seconds']) : null;
        $vipExpire = (int) ($user->getAttribute('vip_expire') ?? 0);
        $outranks = (int) $user->rank < (int) $actor->rank;

        return response()->json([
            'player' => [
                'id' => $id,
                'username' => (string) $user->username,
                'look' => (string) $user->look,
                'motto' => (string) $user->motto,
                'rank' => (int) $user->rank,
                'rank_name' => $this->ranks->name((int) $user->rank),
                'online' => $online,
                'room' => $roomId > 0 ? ['id' => $roomId, 'name' => RoomRefs::many([$roomId])[$roomId] ?? 'Room ' . $roomId] : null,
                'region' => strtoupper((string) ($user->getAttribute('rp_region') ?? '')),
                'created' => (int) $user->account_created,
                'last_online' => (int) $user->last_online,
                'account' => ['id' => $rootId, 'characters' => $characters->map(fn (stdClass $c): array => ['id' => (int) $c->id, 'username' => (string) $c->username, 'look' => (string) $c->look, 'online' => (string) $c->online === '1'])->values()->all()],
                'domain' => $job['domain'] ?? null,
                'job_label' => $job !== null ? $job['corporation']['acronym'] ?: $job['corporation']['name'] : null,
                'flags' => [
                    'banned' => $banned,
                    'muted' => $muted,
                    'jailed' => $jail !== null ? ['left' => (int) $jail->seconds_left, 'sentence' => (int) $jail->sentence_seconds] : null,
                    'vip_until' => $vipExpire > time() ? $vipExpire : null,
                    'ambassador' => (bool) ($user->getAttribute('is_ambassador') ?? false),
                    'staff' => (int) $user->rank >= (int) setting('min_staff_rank', 4),
                ],
                'counts' => [
                    'security' => RoleplaySchema::has('rp_browser_guard_log') ? DB::table('rp_browser_guard_log')->whereIn('user_id', $characters->pluck('id'))->where('created_at', '>=', time() - 7 * 86400)->count() : 0,
                    'discipline' => $this->disciplineCount($rootId),
                ],
            ],
            'overview' => [
                'stats' => $this->stats($id),
                'job' => $job,
                'money' => $this->money($user),
                'relationships' => $this->relationships($user),
                'justice' => JusticeController::playerSummary($id),
                'flags' => $this->accountFlags($rootId, $actor),
            ],
            'can' => [
                'alert' => GameAbilities::allows($actor, 'command_alert_user'),
                'disconnect' => $outranks && GameAbilities::allows($actor, 'command_disconnect'),
                'give_coins' => GameAbilities::allows($actor, 'rp_city_balance'),
                'give_badge' => GameAbilities::allows($actor, 'command_give_badge'),
                'ban' => $outranks && $this->permissions->allows($actor, 'manage_bans'),
                'mute' => $outranks && GameAbilities::allows($actor, 'command_mute'),
                'note' => $this->permissions->allows($actor, 'manage_bans'),
                'see_ips' => $this->permissions->allows($actor, 'edit_user'),
                'chat' => $this->permissions->allows($actor, 'manage_room_chatlogs') || $this->permissions->allows($actor, 'manage_private_chatlogs'),
                'payments' => $this->permissions->allows($actor, 'view_payments'),
            ],
        ]);
    }

    /**
     * The hover card on a player chip.
     */
    public function card(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $job = $this->job($id);
        $flags = [];

        if ($this->activeBan(Account::rootId($user))) {
            $flags[] = 'Banned';
        }

        if (RoleplaySchema::has('rp_jail') && DB::table('rp_jail')->where('user_id', $id)->where('released_at', 0)->exists()) {
            $flags[] = 'Jailed';
        }

        if ((int) ($user->getAttribute('vip_expire') ?? 0) > time()) {
            $flags[] = 'VIP';
        }

        $roomId = (string) $user->online === '1' && RoleplaySchema::hasColumn('users', 'last_room_id') ? (int) $user->getAttribute('last_room_id') : 0;

        return response()->json([
            'id' => $id,
            'username' => (string) $user->username,
            'look' => (string) $user->look,
            'rank_name' => $this->ranks->name((int) $user->rank),
            'online' => (string) $user->online === '1',
            'room' => $roomId > 0 ? (RoomRefs::many([$roomId])[$roomId] ?? null) : null,
            'job' => $job !== null ? sprintf('%s, %s', $job['rank'] ?? 'Employee', $job['corporation']['name']) : null,
            'flags' => $flags,
        ]);
    }

    /**
     * The directory's side peek: the overview in brief and the last few events.
     */
    public function peek(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $rootId = Account::rootId($user);
        /** @var User $actor */
        $actor = $request->user();
        $recent = LogStream::page(
            LogSources::readableBy($actor),
            ['player' => $id],
            null,
            3,
        );

        return response()->json([
            'id' => $id,
            'username' => (string) $user->username,
            'look' => (string) $user->look,
            'online' => (string) $user->online === '1',
            'characters' => count(Account::characterIds($rootId)),
            'account' => $rootId,
            'job' => $this->job($id),
            'stats' => $this->stats($id),
            'money' => $this->money($user),
            'gang' => $this->relationships($user)['gang'],
            'vip' => (int) ($user->getAttribute('vip_expire') ?? 0) > time(),
            'recent' => $recent['rows'],
        ]);
    }

    /**
     * Name, ID, IP or device: the command palette's lookup across players,
     * rooms, corporations and gangs.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['players' => [], 'rooms' => [], 'corporations' => [], 'gangs' => []]);
        }

        $like = addcslashes($q, '\\%_') . '%';
        $players = DB::table('users')
            ->where(fn (Builder $w) => $w->where('username', 'like', $like)->when(ctype_digit($q), fn (Builder $id) => $id->orWhere('id', (int) $q)))
            ->orderByRaw('username = ? DESC', [$q])
            ->orderByDesc('online')
            ->limit(6)
            ->get(['id', 'username', 'look', 'online']);
        $title = RoleplaySchema::roomTitleColumn();
        $rooms = DB::table('rooms')
            ->where(fn (Builder $w) => $w->where($title, 'like', '%' . addcslashes($q, '\\%_') . '%')->when(ctype_digit($q), fn (Builder $id) => $id->orWhere('id', (int) $q)))
            ->orderByDesc('users_now')
            ->limit(4)
            ->get(['id', $title . ' as name']);

        return response()->json([
            'players' => $players->map(fn (stdClass $p): array => ['id' => (int) $p->id, 'username' => (string) $p->username, 'look' => (string) $p->look, 'online' => (string) $p->online === '1'])->values(),
            'rooms' => $rooms->map(fn (stdClass $r): array => ['id' => (int) $r->id, 'name' => (string) $r->name])->values(),
            'corporations' => RoleplaySchema::has('rp_corporations')
                ? DB::table('rp_corporations')->where(fn (Builder $w) => $w->where('name', 'like', '%' . addcslashes($q, '\\%_') . '%')->orWhere('acronym', 'like', $like))->limit(4)->get(['id', 'name', 'is_police'])->map(fn (stdClass $c): array => ['id' => (int) $c->id, 'name' => (string) $c->name, 'police' => (bool) $c->is_police])->values()
                : [],
            'gangs' => RoleplaySchema::hasColumn('groups', 'is_gang')
                ? DB::table('groups')->where('is_gang', '1')->where('name', 'like', '%' . addcslashes($q, '\\%_') . '%')->limit(4)->get(['id', 'name'])->map(fn (stdClass $g): array => ['id' => (int) $g->id, 'name' => (string) $g->name])->values()
                : [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private function filter(Builder $query, array $f, bool $canSeeIps): void
    {
        $q = trim((string) ($f['q'] ?? ''));

        if ($q !== '') {
            $like = addcslashes($q, '\\%_') . '%';
            $byIp = $canSeeIps && filter_var($q, FILTER_VALIDATE_IP) !== false;
            $byDevice = $canSeeIps && preg_match('/^[a-f0-9]{8}([a-f0-9]{8}|[a-f0-9]{24})?$/', $q) === 1;
            $query->where(function (Builder $where) use ($q, $like, $byIp, $byDevice): void {
                $where->where('u.username', 'like', $like);

                if (ctype_digit($q)) {
                    $where->orWhere('u.id', (int) $q);
                }

                if ($byIp) {
                    $where->orWhere('u.' . PlayerColumns::currentIp(), $q)
                        ->orWhere('u.' . PlayerColumns::registerIp(), $q);

                    if (RoleplaySchema::has('website_connection_logs')) {
                        $where->orWhereIn('u.id', DB::table('website_connection_logs')->where('ip_address', $q)->select('character_id'))
                            ->orWhereIn('u.id', DB::table('website_connection_logs')->where('ip_address', $q)->select('user_id'));
                    }
                }

                if ($byDevice && RoleplaySchema::has('website_connection_logs')) {
                    $where->orWhereIn('u.id', DB::table('website_connection_logs')
                        ->where(fn (Builder $match) => $match->where('device_id', 'like', $q . '%')->orWhere('hardware_hash', $q))
                        ->select('user_id'));
                }
            });
        }

        match ($f['status'] ?? null) {
            'online' => $query->where('u.online', '1'),
            'offline' => $query->where('u.online', '<>', '1'),
            default => null,
        };

        if (isset($f['rank']) && $f['rank'] !== '') {
            $query->where('u.rank', (int) $f['rank']);
        }

        if (! empty($f['corp']) && RoleplaySchema::has('rp_corporation_employees')) {
            $query->whereIn('u.id', DB::table('rp_corporation_employees')->where('corporation_id', (int) $f['corp'])->select('user_id'));
        }

        if (! empty($f['gang']) && RoleplaySchema::has('rp_gang_members')) {
            $query->whereIn('u.id', DB::table('rp_gang_members')->where('gang_id', (int) $f['gang'])->select('user_id'));
        }

        if (! empty($f['region']) && RoleplaySchema::hasColumn('users', 'rp_region')) {
            $query->where('u.rp_region', strtoupper((string) $f['region']));
        }

        if (! empty($f['created'])) {
            $query->where('u.account_created', '>=', (string) (time() - (int) $f['created'] * 86400));
        }

        match ($f['flag'] ?? null) {
            'jailed' => RoleplaySchema::has('rp_jail') ? $query->whereIn('u.id', DB::table('rp_jail')->where('released_at', 0)->select('user_id')) : $query->whereRaw('1 = 0'),
            'banned' => $this->whereBanned($query),
            'vip' => $query->where('u.vip_expire', '>', time()),
            'staff' => $query->where('u.rank', '>=', (int) setting('min_staff_rank', 4)),
            'muted' => $query->where('u.time_muted', '>', 0),
            'flagged' => RoleplaySchema::has('rp_browser_guard_log') ? $query->whereIn('u.id', DB::table('rp_browser_guard_log')->where('created_at', '>=', time() - 7 * 86400)->select('user_id')) : $query->whereRaw('1 = 0'),
            default => null,
        };
    }

    private function whereBanned(Builder $query): void
    {
        $query->whereExists(function (Builder $sub): void {
            $sub->selectRaw('1')->from('bans');

            if (PlayerColumns::isPlus()) {
                $sub->whereColumn('bans.value', 'u.username')->where('bans.bantype', 'user')->where('bans.expire', '>', time());
            } else {
                $sub->whereColumn('bans.user_id', 'u.id')->where('bans.ban_expire', '>', time());
            }
        });
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     *
     * @return list<array<string, mixed>>
     */
    private function rows($rows): array
    {
        $ids = $rows->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $roots = $rows->mapWithKeys(fn (stdClass $r): array => [(int) $r->id => (int) ($r->parent_id ?: $r->id)])->all();
        $siblings = RoleplaySchema::hasColumn('users', 'parent_id') && $roots !== []
            ? DB::table('users')->whereIn('parent_id', array_values(array_unique($roots)))->selectRaw('parent_id, COUNT(*) AS n')->groupBy('parent_id')->pluck('n', 'parent_id')
            : collect();

        $jobs = RoleplaySchema::has('rp_corporation_employees') && $ids !== []
            ? DB::table('rp_corporation_employees as e')
                ->join('rp_corporations as c', 'c.id', '=', 'e.corporation_id')
                ->leftJoin('rp_corporation_ranks as r', 'r.id', '=', 'e.rank_id')
                ->whereIn('e.user_id', $ids)
                ->get(['e.user_id', 'c.id', 'c.name', 'c.acronym', 'c.is_police', 'c.service_type', 'r.name as rank'])
                ->keyBy('user_id')
            : collect();
        $gangs = RoleplaySchema::has('rp_gang_members') && $ids !== []
            ? DB::table('rp_gang_members as m')->join('groups as g', 'g.id', '=', 'm.gang_id')->whereIn('m.user_id', $ids)->get(['m.user_id', 'g.id', 'g.name'])->keyBy('user_id')
            : collect();
        $banks = RoleplaySchema::has('rp_bank_accounts') && $ids !== []
            ? DB::table('rp_bank_accounts')->whereIn('user_id', $ids)->selectRaw('user_id, current_balance + savings_balance AS total')->pluck('total', 'user_id')
            : collect();
        $jailed = RoleplaySchema::has('rp_jail') && $ids !== []
            ? array_flip(DB::table('rp_jail')->whereIn('user_id', $ids)->where('released_at', 0)->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all())
            : [];
        $flagged = RoleplaySchema::has('rp_browser_guard_log') && $ids !== []
            ? array_flip(DB::table('rp_browser_guard_log')->whereIn('user_id', $ids)->where('created_at', '>=', time() - 7 * 86400)->distinct()->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all())
            : [];
        $bannedNames = PlayerColumns::isPlus() && $rows->isNotEmpty()
            ? array_flip(DB::table('bans')->where('bantype', 'user')->where('expire', '>', time())->whereIn('value', $rows->pluck('username'))->pluck('value')->map(fn (mixed $v): string => strtolower((string) $v))->all())
            : [];
        $staffRank = (int) setting('min_staff_rank', 4);
        $vip = RoleplaySchema::hasColumn('users', 'vip_expire') && $ids !== []
            ? DB::table('users')->whereIn('id', $ids)->where('vip_expire', '>', time())->pluck('id')->map(fn (mixed $id): int => (int) $id)->flip()->all()
            : [];

        return array_values($rows->map(function (stdClass $row) use ($roots, $siblings, $jobs, $gangs, $banks, $jailed, $flagged, $bannedNames, $staffRank, $vip): array {
            $id = (int) $row->id;
            $root = $roots[$id];
            $job = $jobs->get($id);
            $gang = $gangs->get($id);
            $flag = match (true) {
                isset($bannedNames[strtolower((string) $row->username)]) => ['Banned', 'bad'],
                isset($jailed[$id]) => ['Jailed', 'warn'],
                isset($flagged[$id]) => ['Flagged', 'info'],
                (int) $row->rank >= $staffRank => ['Staff', 'brand'],
                isset($vip[$id]) => ['VIP', 'acc'],
                (int) $row->account_created >= time() - 7 * 86400 => ['New', 'ok'],
                default => null,
            };

            return [
                'id' => $id,
                'username' => (string) $row->username,
                'look' => (string) $row->look,
                'online' => (string) $row->online === '1',
                'last_online' => (int) $row->last_online,
                'account' => $root,
                'siblings' => (int) ($siblings->get($root) ?? 0),
                'job' => $job !== null ? ['corporation' => ['id' => (int) $job->id, 'name' => (string) $job->name, 'acronym' => (string) $job->acronym], 'rank' => (string) ($job->rank ?? ''), 'domain' => CorporationController::domain((bool) $job->is_police, (string) $job->service_type)] : null,
                'gang' => $gang !== null ? ['id' => (int) $gang->id, 'name' => (string) $gang->name] : null,
                'coins' => (int) $row->credits,
                'bank' => $banks->has($id) ? (int) $banks->get($id) : null,
                'flag' => $flag !== null ? ['label' => $flag[0], 'tone' => $flag[1]] : null,
            ];
        })->all());
    }

    /**
     * @return array{characters: int, accounts: int, online: int}
     */
    private function summary(): array
    {
        /** @var array{characters: int, accounts: int, online: int} $summary */
        $summary = Cache::remember('housekeeping:player-summary', now()->addSeconds(30), fn (): array => [
            'characters' => DB::table('users')->count(),
            'accounts' => RoleplaySchema::hasColumn('users', 'parent_id') ? DB::table('users')->whereNull('parent_id')->orWhere('parent_id', 0)->count() : DB::table('users')->count(),
            'online' => DB::table('users')->where('online', '1')->count(),
        ]);

        return $summary;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function job(int $id): ?array
    {
        if (! RoleplaySchema::has('rp_corporation_employees')) {
            return null;
        }

        $job = DB::table('rp_corporation_employees as e')
            ->join('rp_corporations as c', 'c.id', '=', 'e.corporation_id')
            ->leftJoin('rp_corporation_ranks as r', 'r.id', '=', 'e.rank_id')
            ->where('e.user_id', $id)
            ->first(['c.id', 'c.name', 'c.acronym', 'c.badge', 'c.is_police', 'c.service_type', 'r.id as rank_id', 'r.name as rank_name', 'r.pay', 'r.tiers', 'e.tier', 'e.hired_at', 'e.shift_seconds', 'e.shift_seconds_week', 'e.on_duty']);

        if ($job === null) {
            return null;
        }

        $hq = RoleplaySchema::has('rp_hq_room_ranks') && $job->rank_id !== null
            ? DB::table('rp_hq_room_ranks')->where('rank_id', $job->rank_id)->pluck('room_id')
            : collect();
        $rooms = RoomRefs::many($hq);

        return [
            'corporation' => ['id' => (int) $job->id, 'name' => (string) $job->name, 'acronym' => (string) $job->acronym, 'badge' => (string) $job->badge],
            'domain' => CorporationController::domain((bool) $job->is_police, (string) $job->service_type),
            'rank' => $job->rank_name !== null ? (string) $job->rank_name : null,
            'tier' => (int) $job->tier,
            'tiers' => (int) ($job->tiers ?? 0),
            'pay' => (int) ($job->pay ?? 0),
            'hired_at' => (int) $job->hired_at,
            'shift_week' => (int) $job->shift_seconds_week,
            'shift_total' => (int) $job->shift_seconds,
            'on_duty' => (int) $job->on_duty === 1,
            'hq' => array_values(array_map(fn (int $room, string $name): array => ['id' => $room, 'name' => $name], array_keys($rooms), $rooms)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stats(int $id): ?array
    {
        if (! RoleplaySchema::has('user_rp_stats')) {
            return null;
        }

        $s = DB::table('user_rp_stats')->where('user_id', $id)->first();

        if ($s === null) {
            return null;
        }

        return [
            'health' => (int) $s->health,
            'health_max' => max(1, (int) $s->health_max),
            'energy' => (int) $s->energy,
            'energy_max' => max(1, (int) $s->energy_max),
            'strength' => (int) $s->strength_level,
            'stamina' => (int) $s->stamina_level,
            'online_seconds' => (int) $s->online_seconds,
            // gym_until is a reading of the stats' own online clock.
            'gym_left' => max(0, (int) $s->gym_until - (int) $s->online_seconds),
            'passive_left' => (int) $s->passive_seconds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function money(User $user): array
    {
        $bank = RoleplaySchema::has('rp_bank_accounts') ? DB::table('rp_bank_accounts')->where('user_id', $user->id)->first() : null;

        return [
            'coins' => (int) $user->credits,
            'diamonds' => (int) ($user->getAttribute('vip_points') ?? 0),
            'current' => $bank !== null ? (int) $bank->current_balance : null,
            'savings' => $bank !== null ? (int) $bank->savings_balance : null,
            'interest_total' => $bank !== null ? (int) $bank->interest_total : 0,
            'wages_total' => $bank !== null ? (int) $bank->wages_total : 0,
            'online' => (string) $user->online === '1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function relationships(User $user): array
    {
        $partner = RoleplaySchema::has('rp_partnerships') ? DB::table('rp_partnerships')->where('user_id', $user->id)->first() : null;
        $gang = RoleplaySchema::has('rp_gang_members')
            ? DB::table('rp_gang_members as m')
                ->join('groups as g', 'g.id', '=', 'm.gang_id')
                ->leftJoin('rp_gang_roles as r', 'r.id', '=', 'm.role_id')
                ->where('m.user_id', $user->id)
                ->first(['g.id', 'g.name', 'g.owner_id', 'r.name as role', 'm.joined_at'])
            : null;
        $birthday = RoleplaySchema::has('rp_user_birthdays') ? DB::table('rp_user_birthdays')->where('user_id', $user->id)->first() : null;
        $privacy = RoleplaySchema::has('rp_user_privacy') ? DB::table('rp_user_privacy')->where('user_id', $user->id)->first() : null;
        $refs = PlayerRefs::many([$partner->partner_id ?? 0]);
        // rp_user_privacy: 0 no one, 1 contacts (birthday) or custom (region), 2 everyone.
        $birthdayVisibility = fn (mixed $v): string => match ((int) $v) {
            0 => 'No one',
            1 => 'Contacts',
            default => 'Everyone',
        };
        $regionVisibility = fn (mixed $v): string => match ((int) $v) {
            0 => 'No one',
            1 => 'Colleagues and friends',
            default => 'Everyone',
        };

        return [
            'partner' => $partner !== null ? ['player' => PlayerRefs::pick($refs, $partner->partner_id), 'since' => (int) $partner->since] : null,
            'gang' => $gang !== null ? ['id' => (int) $gang->id, 'name' => (string) $gang->name, 'role' => (int) $gang->owner_id === (int) $user->id ? 'Leader' : (string) ($gang->role ?? 'Member'), 'joined_at' => (int) $gang->joined_at] : null,
            'birthday' => $birthday !== null ? ['month' => (int) $birthday->month, 'day' => (int) $birthday->day, 'visibility' => $privacy !== null ? $birthdayVisibility($privacy->birthday_visibility) : 'Contacts'] : null,
            'region_visibility' => $privacy !== null ? $regionVisibility($privacy->region_visibility) : 'Everyone',
        ];
    }

    /**
     * Guard flags, staff notes and active bans across the whole account.
     *
     * @return list<array<string, mixed>>
     */
    private function accountFlags(int $rootId, User $actor): array
    {
        $ids = Account::characterIds($rootId);
        $flags = [];

        if (RoleplaySchema::has('rp_browser_guard_log') && $this->permissions->allows($actor, 'edit_user')) {
            $guard = DB::table('rp_browser_guard_log')->whereIn('user_id', $ids)->orderByDesc('created_at')->first(['reason', 'detail', 'created_at', 'user_id']);

            if ($guard !== null) {
                $flags[] = ['kind' => 'guard', 'tone' => 'info', 'title' => 'Browser guard · ' . strtolower(LogSources::guardReason((int) $guard->reason)), 'sub' => (string) $guard->detail, 'at' => (int) $guard->created_at, 'to' => 'security'];
            }
        }

        if (RoleplaySchema::has('website_staff_notes')) {
            $notes = DB::table('website_staff_notes')->where('account_id', $rootId);
            $count = (clone $notes)->count();
            $latest = (clone $notes)->orderByDesc('pinned')->orderByDesc('created_at')->first(['body', 'created_at']);

            if ($latest !== null) {
                $flags[] = ['kind' => 'note', 'tone' => '', 'title' => sprintf('%d staff %s', $count, $count === 1 ? 'note' : 'notes'), 'sub' => mb_strimwidth((string) $latest->body, 0, 90, '…'), 'at' => strtotime((string) $latest->created_at) ?: null, 'to' => 'discipline'];
            }
        }

        $ban = $this->activeBan($rootId);

        if ($ban !== null) {
            $flags[] = ['kind' => 'ban', 'tone' => 'bad', 'title' => 'Banned', 'sub' => $ban['reason'], 'at' => $ban['expires_at'], 'to' => 'discipline'];
        }

        return $flags;
    }

    /**
     * The account's active ban, whichever character carries it.
     *
     * @return array{reason: string, expires_at: int, added_by: string}|null
     */
    private function activeBan(int $rootId): ?array
    {
        if (! PlayerColumns::isPlus()) {
            $ban = DB::table('bans')->whereIn('user_id', Account::characterIds($rootId))->where('ban_expire', '>', time())->orderByDesc('ban_expire')->first();

            return $ban !== null ? ['reason' => (string) $ban->ban_reason, 'expires_at' => (int) $ban->ban_expire, 'added_by' => (string) $ban->user_staff_id] : null;
        }

        $ban = DB::table('bans')
            ->where('bantype', 'user')
            ->whereIn('value', Account::usernames($rootId))
            ->where('expire', '>', time())
            ->orderByDesc('expire')
            ->first();

        return $ban !== null ? ['reason' => (string) $ban->reason, 'expires_at' => (int) $ban->expire, 'added_by' => (string) $ban->added_by] : null;
    }

    private function disciplineCount(int $rootId): int
    {
        $n = $this->activeBan($rootId) !== null ? 1 : 0;

        if (RoleplaySchema::has('website_staff_notes')) {
            $n += DB::table('website_staff_notes')->where('account_id', $rootId)->count();
        }

        return $n;
    }
}
