<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Emulator\Data\Feature;
use App\Emulator\Emulator;
use App\Http\Controllers\Controller;
use App\Models\Community\Staff\WebsiteStaffApplications;
use App\Models\User;
use App\Models\WebsiteDrawBadge;
use App\Models\WebsiteHousekeepingPermission;
use App\Services\HousekeepingPermissionsService;
use App\Support\Housekeeping\RankDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the panel needs before it can draw its shell: who is signed in,
 * which capabilities they hold, the rank ladder, and the handful of settings
 * the client renders with (imager URL, badge path, hotel name).
 */
class MeController extends Controller
{
    public function __construct(
        private readonly HousekeepingPermissionsService $permissions,
        private readonly RankDirectory $ranks,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $granted = [];
        foreach (WebsiteHousekeepingPermission::query()->pluck('permission') as $permission) {
            $granted[(string) $permission] = $this->permissions->allows($user, (string) $permission);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'look' => (string) $user->look,
                'motto' => (string) $user->motto,
                'rank' => (int) $user->rank,
                'rank_name' => $this->ranks->name((int) $user->rank),
                'last_online' => (int) $user->last_online,
            ],
            'permissions' => $granted,
            'ranks' => $this->ranks->all()->values(),
            'config' => [
                'hotel_name' => (string) setting('hotel_name', 'PixelRP'),
                'imager' => (string) setting('avatar_imager', '/imaging/?figure='),
                'badges_path' => (string) setting('badges_path', ''),
                'emulator' => Emulator::driver(),
                'features' => collect(Feature::cases())
                    ->filter(fn (Feature $feature): bool => Emulator::supports($feature))
                    ->map(fn (Feature $feature): string => $feature->value)
                    ->values(),
                'legacy_url' => url('/housekeeping/legacy'),
                'min_staff_rank' => (int) setting('min_staff_rank', 4),
            ],
            'counts' => [
                'staff_applications' => WebsiteStaffApplications::query()->where('status', 'pending')->count(),
                'draw_badges' => WebsiteDrawBadge::query()->where('published', 0)->count(),
            ],
        ]);
    }
}
