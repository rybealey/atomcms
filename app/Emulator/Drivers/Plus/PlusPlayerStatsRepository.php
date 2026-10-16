<?php

namespace App\Emulator\Drivers\Plus;

use App\Emulator\Contracts\PlayerStatsRepository;
use App\Emulator\Data\LeaderboardEntry;
use App\Emulator\Data\Stat;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Plus EMU stores per-player statistics in user_statistics, keyed by the
 * user id, with differently cased column names.
 */
class PlusPlayerStatsRepository implements PlayerStatsRepository
{
    public function topBy(Stat $stat, int $limit, array $excludeUserIds = []): Collection
    {
        $column = $this->column($stat);

        $rows = DB::table('user_statistics')
            ->whereNotIn('id', $excludeUserIds)
            ->orderByDesc($column)
            ->limit($limit)
            ->get(['id', $column]);

        $users = User::whereKey($rows->pluck('id')->all())
            ->get(['id', 'username', 'look'])
            ->keyBy('id');

        $entries = [];
        foreach ($rows as $row) {
            $user = $users->get($row->id);
            if ($user !== null) {
                $entries[] = new LeaderboardEntry($user, (int) $row->{$column});
            }
        }

        return collect($entries);
    }

    public function valueOf(User $user, Stat $stat): int
    {
        return (int) (DB::table('user_statistics')->where('id', $user->id)->value($this->column($stat)) ?? 0);
    }

    public function rankOf(User $user, Stat $stat, array $excludeUserIds = []): int
    {
        $column = $this->column($stat);

        return DB::table('user_statistics')
            ->whereNotIn('id', $excludeUserIds)
            ->where($column, '>', $this->valueOf($user, $stat))
            ->count() + 1;
    }

    private function column(Stat $stat): string
    {
        return match ($stat) {
            Stat::OnlineTime => 'OnlineTime',
            Stat::RespectsReceived => 'Respect',
            Stat::AchievementScore => 'AchievementScore',
        };
    }
}
