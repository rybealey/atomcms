<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Articles\WebsiteArticle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MeController extends Controller
{
    public function __invoke(): View
    {
        $user = Auth::user()?->load('permission:id,rank_name', 'currencies');

        return view('user.me', [
            'onlineFriends' => $user?->getOnlineFriends(),
            'user' => $user,
            'articles' => WebsiteArticle::whereHas('user')->with('user:id,username,look')->latest()->take(5)->get(),
            'cash' => (int) ($user?->credits ?? 0),
            'bank' => (int) $this->bankBalance($user?->id),
            'diamonds' => (int) ($user?->currency('diamonds') ?? 0),
            'stats' => $this->playerStats($user?->id),
            'employment' => $this->employment($user?->id),
        ]);
    }

    private function bankBalance(?int $userId): int
    {
        if (! $userId) return 0;

        return (int) (DB::table('rp_player_bank')
            ->where('habbo_id', $userId)
            ->value('balance') ?? 0);
    }

    private function playerStats(?int $userId): array
    {
        $defaults = ['hp' => 100, 'max_hp' => 100, 'energy' => 100, 'max_energy' => 100, 'level' => 1, 'is_on_duty' => false];

        if (! $userId) return $defaults;

        $row = DB::table('rp_player_stats')->where('habbo_id', $userId)->first();
        if (! $row) return $defaults;

        return [
            'hp' => (int) $row->hp,
            'max_hp' => (int) $row->max_hp,
            'energy' => (int) $row->energy,
            'max_energy' => (int) $row->max_energy,
            'level' => (int) $row->level,
            'is_on_duty' => (bool) $row->is_on_duty,
        ];
    }

    private function employment(?int $userId): ?array
    {
        if (! $userId) return null;

        $row = DB::table('rp_corporation_members AS m')
            ->join('rp_corporations AS c', 'c.id', '=', 'm.corp_id')
            ->leftJoin('rp_corporation_ranks AS r', function ($join) {
                $join->on('r.corp_id', '=', 'm.corp_id')->on('r.rank_num', '=', 'm.rank_num');
            })
            ->where('m.habbo_id', $userId)
            ->select('c.id AS corp_id', 'c.name AS corp_name', 'c.corp_key', 'c.badge_code', 'r.title AS rank_title', 'r.salary', 'm.worked_minutes_in_cycle', 'm.hired_at')
            ->first();

        if (! $row) return null;

        // Shift counts are scoped to the *current* employment relationship.
        // rp_corporation_members.hired_at is the cutoff: leaving (fire/quit)
        // deletes the row, a rehire writes a new hired_at, and the COUNT(*)
        // below excludes paychecks credited before the new hire date even
        // though those rp_money_ledger rows are preserved for audit.
        // ref_id = corp_id keeps a multi-corp player's gang paychecks from
        // leaking into job shifts (matches the in-game profile rail).
        $totalShifts = (int) DB::table('rp_money_ledger')
            ->where('habbo_id', $userId)
            ->whereIn('reason', ['paycheck_bank', 'paycheck_cash'])
            ->where('ref_id', $row->corp_id)
            ->where('created_at', '>=', $row->hired_at)
            ->count();

        // Carbon comparison so "max(hired_at, 7d ago)" works regardless of
        // whether $row->hired_at came back as a Carbon or a raw string.
        $hiredAt = \Carbon\Carbon::parse($row->hired_at);
        $weekAgo = now()->subDays(7);
        $weeklyCutoff = $hiredAt->greaterThan($weekAgo) ? $hiredAt : $weekAgo;
        $weeklyShifts = (int) DB::table('rp_money_ledger')
            ->where('habbo_id', $userId)
            ->whereIn('reason', ['paycheck_bank', 'paycheck_cash'])
            ->where('ref_id', $row->corp_id)
            ->where('created_at', '>=', $weeklyCutoff)
            ->count();

        return [
            'corp_id' => (int) $row->corp_id,
            'corp_name' => (string) $row->corp_name,
            'corp_key' => (string) $row->corp_key,
            'badge_code' => $row->badge_code,
            'rank_title' => (string) ($row->rank_title ?? '—'),
            'salary' => (int) ($row->salary ?? 0),
            'minutes_in_cycle' => (int) $row->worked_minutes_in_cycle,
            'hired_at' => $row->hired_at,
            'weekly_shifts' => $weeklyShifts,
            'total_shifts' => $totalShifts,
        ];
    }
}
