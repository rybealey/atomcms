<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Tier B asset gate: mint the signed cookie that authorizes cdn.<domain>.
 *
 * The Nitro client loads every avatar/furni/figuredata asset from the cdn
 * subdomain, whose nginx vhost 403s any request without a valid secure_link
 * cookie. This middleware sets that cookie on authenticated web responses, so
 * only a logged-in player can pull assets — a scraper with a perfect browser
 * fingerprint but no session is locked out, and a misbehaving account can be
 * banned or rate-limited.
 *
 * The digest must byte-for-byte match nginx `secure_link_md5 "<secret><exp>"`:
 *   base64url( md5_raw( secret . exp ) ), padding stripped.
 * The hash deliberately omits the URI so one cookie authorizes the whole asset
 * subtree (per-URL signing is impossible against Nitro's templated asset URLs).
 */
class CdnAssetCookieMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $secret = (string) config('cdn.secret', '');

        // No secret (dev) or no session → never hand out an asset pass. Gating
        // on Auth::check() is what makes the cdn host "session-bound".
        if ($secret === '' || ! Auth::check()) {
            return $response;
        }

        // Refresh only when the current cookie is missing or within an hour of
        // expiry, so we are not re-issuing Set-Cookie on every single request.
        $existing = (int) $request->cookie('cdn_e', 0);
        if ($existing > time() + 3600) {
            return $response;
        }

        $exp  = time() + (int) config('cdn.ttl', 86400);
        $hash = rtrim(strtr(base64_encode(md5($secret . $exp, true)), '+/', '-_'), '=');

        // Scope/secure to match the session cookie so it rides same-site
        // subresource loads to cdn.<domain>. Sent RAW (see EncryptCookies
        // $except) and HttpOnly (JS never needs them).
        $domain = config('session.domain');
        $secure = (bool) config('session.secure');

        $response->headers->setCookie(
            new Cookie('cdn_e', (string) $exp, $exp, '/', $domain, $secure, true, false, Cookie::SAMESITE_LAX)
        );
        $response->headers->setCookie(
            new Cookie('cdn_h', $hash, $exp, '/', $domain, $secure, true, false, Cookie::SAMESITE_LAX)
        );

        return $response;
    }
}
