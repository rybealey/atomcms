<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * pixelrp: the pass every game file request must carry (docs/asset-protection-plan.md).
 *
 * Three cookies, checked by nginx's secure_link module (docker/web/asset-gate.conf)
 * with no PHP on the hot path: the user id, an expiry, and an MD5 over both and
 * the shared secret, base64url without padding - the exact string
 * `secure_link_md5 "$secure_link_expires$cookie_prp_ap_u $asset_pass_secret"`
 * computes. Scoped to /nitro-assets/, so the CMS never receives them, and listed
 * in EncryptCookies::$except - an encrypted value is ciphertext to nginx and
 * every file would 403.
 *
 * Issued when /game loads and renewed by the client every half hour
 * (GET /game/asset-pass). Banned accounts never reach either route
 * (check.ban), so a ban stops the renewals and the pass dies within its TTL.
 */
final class AssetPass
{
    public const USER = 'prp_ap_u';

    public const EXPIRES = 'prp_ap_e';

    public const SIGNATURE = 'prp_ap_s';

    private const PATH = '/nitro-assets/';

    /** @return list<Cookie> Nothing when no secret is configured. */
    public static function issue(User $user, Request $request): array
    {
        $secret = (string) config('services.asset_pass.secret');

        if ($secret === '') {
            return [];
        }

        $ttl = max(60, (int) config('services.asset_pass.ttl'));
        $expires = time() + $ttl;
        $values = [
            self::USER => (string) $user->id,
            self::EXPIRES => (string) $expires,
            self::SIGNATURE => self::sign($user->id, $expires, $secret),
        ];

        $cookies = [];

        foreach ($values as $name => $value) {
            $cookies[] = Cookie::create($name, $value)
                ->withExpires($expires)
                ->withPath(self::PATH)
                ->withSecure($request->isSecure())
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX);
        }

        return $cookies;
    }

    public static function sign(int $userId, int $expires, string $secret): string
    {
        return rtrim(strtr(base64_encode(md5("{$expires}{$userId} {$secret}", true)), '+/', '-_'), '=');
    }
}
