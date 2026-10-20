<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

/**
 * pixelrp: where an account enters the hotel from - its IPs and devices, for
 * staff on the housekeeping player page (website_connection_logs,
 * App\Support\Housekeeping\ConnectionReport).
 *
 * Two writes per /game load. NitroController records the visit from what the
 * request itself says (IP, Cloudflare's country, user agent, languages), then
 * the game page's browser reports its own specs (POST /game/device, the
 * script in client/nitro.blade.php) - screen, graphics card, CPU cores,
 * memory, OS version and the rest - onto the same row.
 *
 * A device is a random id in a long-lived cookie, so the same browser is
 * known again across IPs and accounts. One row per account, IP and device: a
 * return visit bumps last_seen_at and visits.
 *
 * Never stands between a player and the city: a failed write is reported
 * and the page carries on.
 */
final class ConnectionLog
{
    public const DEVICE_COOKIE = 'prp_device';

    private const TABLE = 'website_connection_logs';

    private const DEVICE_COOKIE_YEARS = 5;

    /** What the game page may report about itself - anything else is dropped. */
    private const SPEC_KEYS = [
        'screen', 'viewport', 'timezone', 'tzOffset', 'language', 'languages', 'platform', 'vendor',
        'cores', 'memory', 'touch', 'cookies', 'dnt', 'pdf', 'webdriver', 'connection', 'gpu',
        'uaData', 'storageGB', 'media', 'battery', 'colorScheme', 'colorGamut', 'reducedMotion',
    ];

    /** The device id this browser carries, or null when it has none (or not one of ours). */
    public static function deviceId(Request $request): ?string
    {
        $id = $request->cookie(self::DEVICE_COOKIE);

        return is_string($id) && preg_match('/^[a-f0-9]{32}$/', $id) === 1 ? $id : null;
    }

    public static function newDeviceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function deviceCookie(string $deviceId, Request $request): Cookie
    {
        return Cookie::create(self::DEVICE_COOKIE, $deviceId)
            ->withExpires(now()->addYears(self::DEVICE_COOKIE_YEARS))
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    /** One /game load: the row for this account, IP and device is made, or bumped. */
    public static function record(User $account, Request $request, string $deviceId): void
    {
        try {
            $now = now();

            DB::table(self::TABLE)->upsert([[
                'user_id' => $account->id,
                'character_id' => $account->activeCharacter()->id,
                'ip_address' => (string) $request->ip(),
                'device_id' => $deviceId,
                'country' => self::country($request),
                'location' => self::location($request),
                'user_agent' => self::cut($request->userAgent(), 512),
                'accept_language' => self::cut($request->header('Accept-Language'), 255),
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'visits' => 1,
            ]], ['user_id', 'ip_address', 'device_id'], [
                'character_id', 'country', 'location', 'user_agent', 'accept_language', 'last_seen_at',
                'visits' => DB::raw('visits + 1'),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The game page's own report about the browser and machine, kept on this
     * visit's row - made first if the IP moved since /game loaded.
     *
     * @param  array<string, mixed>  $specs  Already through cleanSpecs().
     */
    public static function attachSpecs(User $account, Request $request, string $deviceId, array $specs): void
    {
        try {
            $row = fn () => DB::table(self::TABLE)
                ->where('user_id', $account->id)
                ->where('ip_address', (string) $request->ip())
                ->where('device_id', $deviceId);

            if (! $row()->exists()) {
                self::record($account, $request, $deviceId);
            }

            $row()->update([
                'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'hardware_hash' => self::hardwareHash($specs),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The report as sent, cut down to the keys we ask for: strings to 200
     * characters, lists to 24 entries, three levels deep.
     *
     * @param  array<mixed>  $input
     *
     * @return array<string, mixed>
     */
    public static function cleanSpecs(array $input): array
    {
        $clean = [];

        foreach (self::SPEC_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                $clean[$key] = self::cleanValue($input[$key], 0);
            }
        }

        return $clean;
    }

    /**
     * The same machine, as far as the browser lets on: graphics card, screen,
     * CPU cores, memory, platform and touch. Left out on purpose: anything a
     * player changes in passing (zoom, window size, timezone, network).
     *
     * @param  array<string, mixed>  $specs
     */
    public static function hardwareHash(array $specs): ?string
    {
        $gpu = $specs['gpu']['renderer'] ?? null;
        $screen = is_array($specs['screen'] ?? null) ? $specs['screen'] : [];

        if (! is_string($gpu) || $gpu === '' || ! isset($screen['w'], $screen['h'])) {
            return null;
        }

        $parts = [
            $gpu, $screen['w'], $screen['h'], $specs['cores'] ?? '', $specs['memory'] ?? '',
            $specs['platform'] ?? '', $specs['touch'] ?? '', $specs['uaData']['architecture'] ?? '',
        ];

        return substr(hash('sha256', implode('|', array_map(fn (mixed $part): string => is_scalar($part) ? (string) $part : '', $parts))), 0, 16);
    }

    private static function cleanValue(mixed $value, int $depth): mixed
    {
        if (is_string($value)) {
            return mb_substr($value, 0, 200);
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if (! is_array($value) || $depth >= 3) {
            return null;
        }

        $clean = [];

        foreach (array_slice($value, 0, 24, true) as $key => $item) {
            $clean[is_int($key) ? $key : mb_substr((string) $key, 0, 40)] = self::cleanValue($item, $depth + 1);
        }

        return $clean;
    }

    /** Cloudflare's CF-IPCountry - "T1" is a Tor exit; "XX" (unknown) is left out. */
    private static function country(Request $request): ?string
    {
        $country = strtoupper(trim((string) $request->header('CF-IPCountry')));

        return preg_match('/^[A-Z0-9]{2}$/', $country) === 1 && $country !== 'XX' ? $country : null;
    }

    /** City and region, when Cloudflare's visitor location headers are switched on. */
    private static function location(Request $request): ?string
    {
        $parts = array_filter([trim((string) $request->header('CF-IPCity')), trim((string) $request->header('CF-Region'))]);

        return $parts === [] ? null : self::cut(implode(', ', $parts), 128);
    }

    private static function cut(mixed $value, int $length): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
