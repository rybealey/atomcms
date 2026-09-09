<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Contracts\Rcon;
use App\Emulator\Data\Feature;
use App\Emulator\Emulator;
use App\Http\Controllers\Controller;
use App\Models\Community\Staff\WebsiteStaffApplications;
use App\Models\User;
use App\Models\WebsiteDrawBadge;
use App\Support\Housekeeping\BanScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $bans = Emulator::supports(Feature::BanManagement);
        $today = Carbon::today();

        $registrations = [];
        for ($day = 7; $day >= 0; $day--) {
            $start = $today->copy()->subDays($day);
            $registrations[] = User::query()
                ->where('account_created', '>=', (string) $start->timestamp)
                ->where('account_created', '<', (string) $start->copy()->addDay()->timestamp)
                ->count();
        }

        $pendingApplications = WebsiteStaffApplications::query()->where('status', 'pending')->count();
        $pendingBadges = WebsiteDrawBadge::query()->where('published', 0)->count();
        $expiringBans = $bans ? BanScope::expiringWithin(86400) : 0;

        $attention = [];
        if ($pendingApplications > 0) {
            $oldestAt = WebsiteStaffApplications::query()->where('status', 'pending')->min('created_at');
            $attention[] = [
                'title' => sprintf('%d staff application%s waiting', $pendingApplications, $pendingApplications === 1 ? '' : 's'),
                'sub' => is_string($oldestAt) ? 'Oldest from ' . Carbon::parse($oldestAt)->diffForHumans() : '',
                'tone' => 'warning',
                'to' => '/applications',
            ];
        }
        if ($pendingBadges > 0) {
            $attention[] = [
                'title' => sprintf('%d drawn badge%s to approve', $pendingBadges, $pendingBadges === 1 ? '' : 's'),
                'sub' => 'Submitted by players from the badge creator',
                'tone' => 'warning',
                'to' => '/badges?tab=requests',
            ];
        }
        if ($expiringBans > 0) {
            $attention[] = [
                'title' => sprintf('%d ban%s expire%s in the next 24 hours', $expiringBans, $expiringBans === 1 ? '' : 's', $expiringBans === 1 ? 's' : ''),
                'sub' => 'Review before the players are back',
                'tone' => 'info',
                'to' => '/bans',
            ];
        }

        $activity = Activity::query()
            ->with('causer')
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (Activity $entry): array {
                $causer = $entry->causer;

                return [
                    'id' => $entry->id,
                    'who' => $causer instanceof User ? $causer->username : 'System',
                    'look' => $causer instanceof User ? (string) $causer->look : null,
                    'description' => (string) $entry->description,
                    'event' => (string) $entry->event,
                    'subject' => $entry->subject_type !== null ? class_basename($entry->subject_type) : null,
                    'properties' => $entry->properties,
                    'at' => $entry->created_at?->timestamp,
                ];
            })
            ->values();

        return response()->json([
            'stats' => [
                'online' => User::query()->where('online', '1')->count(),
                'total' => User::query()->count(),
                'registrations_today' => $registrations[7],
                'registrations_week_avg' => (int) round(array_sum(array_slice($registrations, 0, 7)) / 7),
                'registrations_spark' => $registrations,
                'active_bans' => $bans ? BanScope::activeCount() : null,
                'pending_applications' => $pendingApplications,
                'pending_badges' => $pendingBadges,
            ],
            'hotel' => [
                'maintenance_enabled' => (bool) setting('maintenance_enabled'),
                'disable_registration' => (bool) setting('disable_registration'),
                'requires_beta_code' => (bool) setting('requires_beta_code'),
                'min_maintenance_login_rank' => (int) setting('min_maintenance_login_rank', 5),
                'emulator_up' => $this->emulatorUp(),
            ],
            'attention' => $attention,
            'activity' => $activity,
        ]);
    }

    /**
     * RCON reachability, cached briefly: the check opens a socket to the
     * emulator and the dashboard polls.
     */
    private function emulatorUp(): bool
    {
        return (bool) Cache::remember('housekeeping:emulator-up', now()->addSeconds(30), function (): bool {
            try {
                return app(Rcon::class)->isConnected();
            } catch (Throwable) {
                return false;
            }
        });
    }
}
