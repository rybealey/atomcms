<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;

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
