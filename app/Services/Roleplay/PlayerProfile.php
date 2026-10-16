<?php

namespace App\Services\Roleplay;

use App\Data\Roleplay\Friend;
use App\Data\Roleplay\PlayerStats;
use App\Data\Roleplay\Wallet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * What the home page knows about one character: wallet, stats and who of
 * their friends is in the city. Everything is read from the emulator's
 * tables as they are; nothing here is invented.
 */
class PlayerProfile
{
    public function __construct(
        private readonly CorporationDirectory $corporations,
        private readonly GangDirectory $gangs,
    ) {}

    /** "Nurse at Mission General" or "Citizen": the line under a name. */
    public function roleLine(User $user): string
    {
        $job = $this->corporations->employment($user->id);

        if ($job !== null) {
            return __(':rank at :corporation', ['rank' => $job->rank, 'corporation' => $job->corporation]);
        }

        $gang = $this->gangs->membership($user->id);

        if ($gang !== null) {
            return __(':role, :gang', ['role' => $gang->role, 'gang' => $gang->name]);
        }

        return __('Citizen');
    }

    public function wallet(User $user): Wallet
    {
        $bank = RoleplaySchema::has('rp_bank_accounts')
            ? DB::table('rp_bank_accounts')->where('user_id', $user->id)->first(['current_balance', 'savings_balance'])
            : null;

        return new Wallet(
            cash: (int) $user->credits,
            deposits: $bank !== null ? (int) $bank->current_balance + (int) $bank->savings_balance : 0,
            diamonds: $user->currency('diamonds'),
            has_bank: $bank !== null,
        );
    }

    /** The numbers the city keeps on a character. */
    public function stats(User $user): PlayerStats
    {
        $online = RoleplaySchema::has('user_statistics')
            ? (int) (DB::table('user_statistics')->where('id', $user->id)->value('OnlineTime') ?? 0)
            : 0;

        $shift = RoleplaySchema::has('rp_corporation_employees')
            ? (int) (DB::table('rp_corporation_employees')->where('user_id', $user->id)->value('shift_seconds') ?? 0)
            : 0;

        $charges = RoleplaySchema::has('rp_charges')
            ? DB::table('rp_charges')->where('user_id', $user->id)->where('dropped_at', 0)->count()
            : 0;

        $jailed = RoleplaySchema::has('rp_jail')
            ? DB::table('rp_jail')->where('user_id', $user->id)->count()
            : 0;

        $rp = RoleplaySchema::has('user_rp_stats')
            ? DB::table('user_rp_stats')->where('user_id', $user->id)->first()
            : null;

        return new PlayerStats(
            online_hours: intdiv($online, 3600),
            shift_hours: intdiv($shift, 3600),
            open_charges: $charges,
            jailed: $jailed,
            health: (int) ($rp->health ?? 100),
            health_max: max(1, (int) ($rp->health_max ?? 100)),
            energy: (int) ($rp->energy ?? 100),
            energy_max: max(1, (int) ($rp->energy_max ?? 100)),
            strength: (int) ($rp->strength_level ?? 0),
            stamina: (int) ($rp->stamina_level ?? 0),
        );
    }

    /**
     * Friends who are in the city right now.
     *
     * @return Collection<int, Friend>
     */
    public function friendsInTown(User $user, int $limit = 5): Collection
    {
        return DB::table('messenger_friendships as f')
            ->join('users as u', 'u.id', '=', 'f.user_two_id')
            ->where('f.user_one_id', $user->id)
            ->where('u.online', '1')
            ->orderBy('u.username')
            ->limit($limit)
            ->get(['u.id', 'u.username', 'u.look', 'u.motto'])
            ->map(fn (stdClass $row): Friend => new Friend(
                id: (int) $row->id,
                username: (string) $row->username,
                look: (string) $row->look,
                motto: (string) $row->motto,
            ))
            ->values();
    }

    /** @return array{online: int, total: int} */
    public function friendCounts(User $user): array
    {
        $total = DB::table('messenger_friendships')->where('user_one_id', $user->id)->count();
        $online = DB::table('messenger_friendships as f')
            ->join('users as u', 'u.id', '=', 'f.user_two_id')
            ->where('f.user_one_id', $user->id)
            ->where('u.online', '1')
            ->count();

        return ['online' => $online, 'total' => $total];
    }
}
