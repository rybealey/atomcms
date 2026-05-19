<?php

namespace App\Http\Middleware;

use App\Services\HousekeepingPermissionsService;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Auth gate for the domain-locked housekeeping panel (e.g. ase.pixelrp.co).
 *
 * That panel has no Filament login page (it is SSO-only), so Filament's
 * default Authenticate has no login URL and returns a bare 401 to guests.
 * Point unauthenticated visitors at the public site instead; the shared
 * parent-domain session cookie carries them back into the panel once they
 * log in there. The target is a different host, so it cannot loop.
 */
class HousekeepingAuthenticate extends Authenticate
{
    /**
     * TEMPORARY diagnostic: log exactly which factor decides the gate, so we
     * can see why a high-rank user is 403'd in domain mode. Remove once the
     * root cause is fixed.
     */
    protected function authenticate($request, array $guards): void
    {
        try {
            $guard = Filament::auth();
            $user = $guard->user();

            Log::warning('HK-AUTH-DIAG', [
                'host' => $request->getHost(),
                'path' => $request->path(),
                'filament_auth_guard' => Filament::getAuthGuard(),
                'guard_check' => $guard->check(),
                'guard_user_id' => $user?->getAuthIdentifier(),
                'guard_user_class' => $user ? get_class($user) : null,
                'is_filament_user' => $user instanceof FilamentUser,
                'guard_user_rank' => $user->rank ?? null,
                'default_auth_check' => auth()->check(),
                'default_auth_user_id' => auth()->id(),
                'current_panel' => Filament::getCurrentOrDefaultPanel()?->getId(),
                'perms' => app(HousekeepingPermissionsService::class)->permissions->toArray(),
                'can_access' => ($user instanceof FilamentUser)
                    ? $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())
                    : null,
                'app_env' => config('app.env'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('HK-AUTH-DIAG-ERR', ['msg' => $e->getMessage()]);
        }

        parent::authenticate($request, $guards);
    }

    protected function redirectTo($request): ?string
    {
        return $this->publicLoginUrl($request);
    }

    /**
     * Derive the public apex from the housekeeping host by dropping the
     * left-most label: ase.pixelrp.co -> https://pixelrp.co/
     */
    private function publicLoginUrl(Request $request): string
    {
        $labels = explode('.', $request->getHost());

        if (count($labels) > 2) {
            array_shift($labels);
        }

        return 'https://' . implode('.', $labels) . '/';
    }
}
