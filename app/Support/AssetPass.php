<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * pixelrp: the pass every game file request must carry (docs/asset-protection-plan.md).
 *
 * Four cookies, checked by nginx's secure_link module (docker/web/asset-gate.conf)
 * with no PHP on the hot path: the user id, an expiry, the account's tier, and
 * an MD5 over all three and the shared secret, base64url without padding - the
 * exact string
 * `secure_link_md5 "$secure_link_expires $cookie_prp_ap_u $cookie_prp_ap_t $asset_pass_secret"`
 * computes. The fields are space-separated, so each one reads back exactly as
 * it was issued. Scoped to /nitro-assets/, so the CMS never receives them, and
 * listed in EncryptCookies::$except - an encrypted value is ciphertext to nginx
 * and every file would 403.
 *
 * The tier sets the daily furni allowance (docker/web/asset-limit.js): staff
 * (rank >= min_staff_rank) have none, so shop and furni work is never cut
 * off; every other account gets a fixed number of different furni files a
 * day. It is signed with the rest, so it can't be edited, and all four
 * cookies are issued together every time, so a rank change takes effect at
 * the next renewal.
 *
 * Issued when /game loads and renewed by the client every half hour
 * (GET /game/asset-pass). Banned accounts never reach either route
 * (check.ban), so a ban stops the renewals and the pass dies within its TTL.
 */
final class AssetPass
{
    public const USER = 'prp_ap_u';

    public const EXPIRES = 'prp_ap_e';

    public const TIER = 'prp_ap_t';

    public const SIGNATURE = 'prp_ap_s';

    public const TIER_PLAYER = 'player';

    public const TIER_STAFF = 'staff';

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
        $tier = self::tier($user);
        $values = [
            self::USER => (string) $user->id,
            self::EXPIRES => (string) $expires,
            self::TIER => $tier,
            self::SIGNATURE => self::sign($user->id, $expires, $tier, $secret),
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

    public static function tier(User $user): string
    {
        return (int) $user->rank >= (int) setting('min_staff_rank', 4) ? self::TIER_STAFF : self::TIER_PLAYER;
    }

    public static function sign(int $userId, int $expires, string $tier, string $secret): string
    {
        return rtrim(strtr(base64_encode(md5("{$expires} {$userId} {$tier} {$secret}", true)), '+/', '-_'), '=');
    }
}
