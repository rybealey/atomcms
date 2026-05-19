<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The housekeeping panel (served at the root of its own host, e.g.
 * ase.pixelrp.co) is SSO-only. Anyone who reaches that host without an
 * authenticated session is sent to the public site to log in; the
 * shared-parent-domain session cookie then carries them back into the panel.
 *
 * This runs before Filament's auth middleware so guests never reach the
 * Filament login page, and because the redirect target is a different host
 * (the public apex) it cannot loop back into the panel.
 */
class RedirectHousekeepingGuestsToLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        return redirect()->away($this->publicLoginUrl($request));
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
