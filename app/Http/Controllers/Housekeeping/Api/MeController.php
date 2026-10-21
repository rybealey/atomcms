<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Emulator\Data\Feature;
use App\Emulator\Emulator;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebsiteHousekeepingPermission;
use App\Services\HousekeepingPermissionsService;
use App\Support\Housekeeping\GameAbilities;
use App\Support\Housekeeping\Pulse;
use App\Support\Housekeeping\RankDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the panel needs before it can draw its shell: who is signed in,
 * which screens (website_housekeeping_permissions) and in-game abilities
 * (permissions_commands) their rank holds, the rank ladder, the handful of
 * settings the client renders with, which hotel this is, and the pulse the
 * rail counts from.
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
            'abilities' => GameAbilities::forUser($user),
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
                'min_staff_rank' => (int) setting('min_staff_rank', 4),
                'environment' => self::environment(),
            ],
            'pulse' => Pulse::current(),
        ]);
    }

    public function pulse(): JsonResponse
    {
        return response()->json(Pulse::current());
    }

    /**
     * Production, beta (beta.<domain>, a full second hotel refreshed from
     * prod) or a developer's machine. The shell paints beta orange.
     */
    public static function environment(): string
    {
        if (app()->environment('local', 'testing')) {
            return 'local';
        }

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return str_starts_with($host, 'beta.') ? 'beta' : 'production';
    }
}
