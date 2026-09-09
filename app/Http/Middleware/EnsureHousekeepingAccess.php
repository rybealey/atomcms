<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the housekeeping panel and its JSON API on the same rank threshold
 * the Filament panel used: the can_access_housekeeping permission. A route
 * may name a further capability (`housekeeping.access:manage_bans`) that the
 * signed-in staff member must also hold.
 */
class EnsureHousekeepingAccess
{
    public function __construct(private readonly HousekeepingPermissionsService $permissions) {}

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->permissions->allows($user, 'can_access_housekeeping')) {
            if ($request->expectsJson()) {
                abort(403, 'You do not have access to housekeeping.');
            }

            return to_route('welcome');
        }

        if ($permission !== null && ! $this->permissions->allows($user, $permission)) {
            abort(403, sprintf('This action needs the %s capability.', $permission));
        }

        return $next($request);
    }
}
