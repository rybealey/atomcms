<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Emulator\Contracts\BadgeRepository;
use App\Emulator\Contracts\BanRepository;
use App\Emulator\Contracts\CurrencyRepository;
use App\Emulator\Data\Feature;
use App\Emulator\Emulator;
use App\Enums\CurrencyTypes;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use App\Support\Housekeeping\BanScope;
use App\Support\Housekeeping\PlayerColumns;
use App\Support\Housekeeping\RankDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'filter' => ['nullable', 'in:all,online,banned,staff,new'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $filter = (string) ($validated['filter'] ?? 'all');
        $search = trim((string) ($validated['q'] ?? ''));
        $canSeeEmail = $this->permissions->allows($actor, 'edit_user');

        $query = User::query()->select(PlayerColumns::listColumns());
        $this->applyFilter($query, $filter);

        if ($search !== '') {
            $like = addcslashes($search, '\\%_') . '%';
            $query->where(function (Builder $where) use ($like, $search): void {
                $where->where('username', 'like', $like)
                    ->orWhere('mail', 'like', $like)
                    ->orWhere(PlayerColumns::currentIp(), $search);
            });
        }

        $page = $query
            ->orderByDesc('online')
            ->orderByDesc('last_online')
            ->paginate((int) ($validated['per_page'] ?? 25));

        $bannedIds = $this->bannedIds($page->getCollection()->map(fn (User $user): int => (int) $user->id)->values()->all());

        return response()->json([
            'items' => $page->getCollection()->map(fn (User $user): array => $this->summary($user, $canSeeEmail, in_array($user->id, $bannedIds, true)))->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
            'counts' => $this->counts(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = User::query()->findOrFail($id);
        $canEdit = $this->permissions->allows($actor, 'edit_user') && (int) $user->rank < (int) $actor->rank;
        $canSeeEmail = $this->permissions->allows($actor, 'edit_user');

        $currencies = app(CurrencyRepository::class);
        $badges = app(BadgeRepository::class);
        $bans = Emulator::supports(Feature::BanManagement) ? app(BanRepository::class)->activeAccountBan($user) : null;

        $sessions = DB::table('users_session_logs')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (object $row): array => [
                'ip' => (string) $row->ip,
                'browser' => (string) ($row->browser ?? ''),
                'at' => isset($row->created_at) ? strtotime((string) $row->created_at) : null,
            ])
            ->values();

        return response()->json([
            'player' => $this->summary($user, $canSeeEmail, $bans !== null) + [
                'register_ip' => $canSeeEmail ? (string) $user->getAttribute(PlayerColumns::registerIp()) : null,
                'home_room' => (int) $user->home_room,
                'two_factor' => $user->hasEnabledTwoFactorAuthentication(),
                'currencies' => [
                    ['type' => 'credits', 'label' => 'Credits', 'value' => $currencies->balance($user, CurrencyTypes::Credits)],
                    ['type' => 'duckets', 'label' => 'Duckets', 'value' => $currencies->balance($user, CurrencyTypes::Duckets)],
                    ['type' => 'diamonds', 'label' => 'Diamonds', 'value' => $currencies->balance($user, CurrencyTypes::Diamonds)],
                    ['type' => 'points', 'label' => 'Points', 'value' => $currencies->balance($user, CurrencyTypes::Points)],
                ],
                'badges' => array_slice($badges->codes($user), 0, 30),
                'ban' => $bans !== null ? ['reason' => $bans->ban_reason, 'expires_at' => $bans->ban_expire] : null,
                'ban_history' => Emulator::supports(Feature::BanManagement) ? BanScope::historyFor($user->id, $user->username) : [],
                'sessions' => $sessions,
                'roleplay' => $this->roleplay($user),
            ],
            'can' => [
                'edit' => $canEdit,
                'reset_password' => $canEdit && $this->permissions->allows($actor, 'reset_user_password'),
                'ban' => $canEdit && $this->permissions->allows($actor, 'manage_bans'),
                'delete' => $canEdit && $this->permissions->allows($actor, 'delete_user'),
                'chatlogs' => $this->permissions->allows($actor, 'manage_room_chatlogs'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user, bool $withEmail, bool $banned): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'motto' => (string) $user->motto,
            'look' => (string) $user->look,
            'rank' => (int) $user->rank,
            'rank_name' => $this->ranks->name((int) $user->rank),
            'online' => (string) $user->online === '1',
            'last_online' => (int) $user->last_online,
            'account_created' => (int) $user->account_created,
            'mail' => $withEmail ? (string) $user->mail : null,
            'ip' => $withEmail ? (string) $user->getAttribute(PlayerColumns::currentIp()) : null,
            'banned' => $banned,
        ];
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'online' => $query->where('online', '1'),
            'banned' => BanScope::apply($query),
            'staff' => $query->where('rank', '>=', (int) setting('min_staff_rank', 4)),
            'new' => $query->where('account_created', '>=', (string) now()->subDays(7)->timestamp),
            default => $query,
        };
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        /** @var array<string, int> $counts */
        $counts = Cache::remember('housekeeping:player-counts', now()->addSeconds(30), function (): array {
            $counts = [];
            foreach (['all', 'online', 'banned', 'staff', 'new'] as $filter) {
                if ($filter === 'banned' && ! Emulator::supports(Feature::BanManagement)) {
                    $counts[$filter] = 0;

                    continue;
                }
                $query = User::query();
                $this->applyFilter($query, $filter);
                $counts[$filter] = $query->count();
            }

            return $counts;
        });

        return $counts;
    }

    /**
     * @param  list<int>  $ids
     *
     * @return list<int>
     */
    private function bannedIds(array $ids): array
    {
        if ($ids === [] || ! Emulator::supports(Feature::BanManagement)) {
            return [];
        }

        return BanScope::apply(User::query()->whereIn('id', $ids))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The player's corporation job and gang, when the roleplay tables exist.
     *
     * @return array{corporation: array<string, mixed>|null, gang: array<string, mixed>|null}
     */
    private function roleplay(User $user): array
    {
        $result = ['corporation' => null, 'gang' => null];

        if ($this->hasTable('rp_corporation_employees')) {
            $job = DB::table('rp_corporation_employees as e')
                ->join('rp_corporations as c', 'c.id', '=', 'e.corporation_id')
                ->leftJoin('rp_corporation_ranks as r', 'r.id', '=', 'e.rank_id')
                ->where('e.user_id', $user->id)
                ->select(['c.id', 'c.name', 'c.badge', 'r.name as rank_name', 'r.rank_order', 'e.tier', 'e.hired_at', 'e.shift_seconds', 'e.shift_seconds_week', 'e.on_duty'])
                ->first();

            if ($job !== null) {
                $result['corporation'] = [
                    'id' => (int) $job->id,
                    'name' => (string) $job->name,
                    'badge' => (string) $job->badge,
                    'rank_name' => (string) ($job->rank_name ?? ''),
                    'rank_order' => (int) ($job->rank_order ?? 0),
                    'tier' => (int) $job->tier,
                    'hired_at' => (int) $job->hired_at,
                    'shift_seconds' => (int) $job->shift_seconds,
                    'shift_seconds_week' => (int) $job->shift_seconds_week,
                    'on_duty' => (int) $job->on_duty === 1,
                ];
            }
        }

        if ($this->hasTable('rp_gang_members') && $this->hasTable('groups')) {
            $gang = DB::table('rp_gang_members as m')
                ->join('groups as g', 'g.id', '=', 'm.gang_id')
                ->leftJoin('rp_gang_roles as r', 'r.id', '=', 'm.role_id')
                ->where('m.user_id', $user->id)
                ->select(['g.id', 'g.name', 'g.owner_id', 'r.name as role_name', 'm.joined_at'])
                ->first();

            if ($gang !== null) {
                $result['gang'] = [
                    'id' => (int) $gang->id,
                    'name' => (string) $gang->name,
                    'role_name' => (int) $gang->owner_id === $user->id ? 'Leader' : (string) ($gang->role_name ?? 'Member'),
                    'joined_at' => (int) $gang->joined_at,
                ];
            }
        }

        return $result;
    }

    private function hasTable(string $table): bool
    {
        return (bool) Cache::remember('housekeeping:has-table:' . $table, now()->addMinutes(10), fn (): bool => Schema::hasTable($table));
    }
}
