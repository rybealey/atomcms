<?php

namespace App\Support\Housekeeping;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;

/**
 * pixelrp: what the housekeeping player page shows about where an account
 * plays from - its IPs and devices (website_connection_logs, written by
 * App\Support\ConnectionLog), its website log ins (website_login_logs), and
 * the other accounts that share an IP or a device with it.
 *
 * Everything is put into words here - "Chrome 141 on Windows 11", "NVIDIA
 * GeForce RTX 3060" - so the panel only lays it out.
 */
final class ConnectionReport
{
    private const MAX_CONNECTIONS = 50;

    private const MAX_LOGINS = 25;

    private const MAX_LINKED = 25;

    /**
     * Every IP and device the account has entered the hotel from, latest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function connections(User $account): array
    {
        $rows = DB::table('website_connection_logs')
            ->where('user_id', $account->id)
            ->orderByDesc('last_seen_at')
            ->limit(self::MAX_CONNECTIONS)
            ->get();

        $characters = DB::table('users')
            ->whereIn('id', $rows->pluck('character_id')->filter()->unique()->values())
            ->pluck('username', 'id');

        return $rows->map(function (object $row) use ($characters): array {
            $specs = json_decode((string) ($row->specs ?? ''), true);
            $specs = is_array($specs) ? $specs : [];
            $agent = tap(new Agent, fn (Agent $agent) => $agent->setUserAgent((string) ($row->user_agent ?? '')));
            $gpu = self::gpuName(self::str($specs['gpu']['renderer'] ?? null));

            return [
                'ip' => (string) $row->ip_address,
                'country' => $row->country,
                'location' => $row->location,
                'device' => substr((string) $row->device_id, 0, 8),
                'browser' => self::browser($agent, $specs, false),
                'os' => self::os($agent, $specs),
                'type' => self::deviceType($agent, $specs),
                'screen' => self::screen($specs),
                'gpu' => $gpu,
                'reported' => $specs !== [],
                'first_seen' => strtotime((string) $row->first_seen_at) ?: null,
                'last_seen' => strtotime((string) $row->last_seen_at) ?: null,
                'visits' => (int) $row->visits,
                'details' => self::details($row, $specs, $agent, $gpu, $characters[$row->character_id] ?? null),
            ];
        })->values()->all();
    }

    /**
     * The account's website log in attempts, both outcomes, latest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function logins(User $account): array
    {
        return DB::table('website_login_logs')
            ->where('user_id', $account->id)
            ->orderByDesc('created_at')
            ->limit(self::MAX_LOGINS)
            ->get()
            ->map(function (object $row): array {
                $agent = tap(new Agent, fn (Agent $agent) => $agent->setUserAgent((string) ($row->user_agent ?? '')));

                return [
                    'ip' => (string) ($row->ip_address ?? ''),
                    'device' => ($row->user_agent ?? '') === ''
                        ? 'Unknown device'
                        : self::browser($agent, [], false) . ' on ' . self::os($agent, []),
                    'ok' => (bool) $row->succeeded,
                    'method' => (string) $row->method,
                    'at' => strtotime((string) $row->created_at) ?: null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Other accounts that played from the same device (the cookie), the same
     * hardware (the specs' hash), or an IP this account used - IPs from the
     * connection log, website log ins, and both IPs on the user row. Private
     * and loopback addresses never count: they are proxies, not people.
     *
     * @return list<array{id: int, device: bool, hardware: bool, ips: list<string>, last_seen: int|null}>
     */
    public static function linked(User $account): array
    {
        $logs = DB::table('website_connection_logs')->where('user_id', $account->id);
        $devices = (clone $logs)->distinct()->pluck('device_id')->all();
        $hashes = (clone $logs)->whereNotNull('hardware_hash')->distinct()->pluck('hardware_hash')->all();
        $ips = self::publicIps(array_merge(
            (clone $logs)->distinct()->pluck('ip_address')->all(),
            DB::table('website_login_logs')->where('user_id', $account->id)->where('succeeded', true)->distinct()->pluck('ip_address')->all(),
            [$account->getAttribute(PlayerColumns::currentIp()), $account->getAttribute(PlayerColumns::registerIp())],
        ));

        /** @var array<int, array{id: int, device: bool, hardware: bool, ips: array<string, true>, last_seen: int|null}> $found */
        $found = [];
        $note = function (int $id, ?string $ip = null, bool $device = false, bool $hardware = false, ?int $seen = null) use (&$found, $account): void {
            if ($id === (int) $account->id || $id <= 0) {
                return;
            }

            $entry = $found[$id] ?? ['id' => $id, 'device' => false, 'hardware' => false, 'ips' => [], 'last_seen' => null];
            $entry['device'] = $entry['device'] || $device;
            $entry['hardware'] = $entry['hardware'] || $hardware;

            if ($ip !== null) {
                $entry['ips'][$ip] = true;
            }

            if ($seen !== null) {
                $entry['last_seen'] = max($entry['last_seen'] ?? 0, $seen);
            }

            $found[$id] = $entry;
        };

        if ($devices !== [] || $hashes !== [] || $ips !== []) {
            DB::table('website_connection_logs')
                ->where('user_id', '<>', $account->id)
                ->where(function ($query) use ($devices, $hashes, $ips): void {
                    $query->whereIn('device_id', $devices)->orWhereIn('hardware_hash', $hashes)->orWhereIn('ip_address', $ips);
                })
                ->get(['user_id', 'device_id', 'hardware_hash', 'ip_address', 'last_seen_at'])
                ->each(function (object $row) use ($note, $devices, $hashes, $ips): void {
                    $note(
                        (int) $row->user_id,
                        in_array($row->ip_address, $ips, true) ? (string) $row->ip_address : null,
                        in_array($row->device_id, $devices, true),
                        $row->hardware_hash !== null && in_array($row->hardware_hash, $hashes, true),
                        strtotime((string) $row->last_seen_at) ?: null,
                    );
                });
        }

        if ($ips !== []) {
            DB::table('website_login_logs')
                ->where('user_id', '<>', $account->id)
                ->where('succeeded', true)
                ->whereIn('ip_address', $ips)
                ->get(['user_id', 'ip_address', 'created_at'])
                ->each(fn (object $row) => $note((int) $row->user_id, (string) $row->ip_address, seen: strtotime((string) $row->created_at) ?: null));

            foreach ([PlayerColumns::currentIp(), PlayerColumns::registerIp()] as $column) {
                DB::table('users')
                    ->whereNull('parent_id')
                    ->where('id', '<>', $account->id)
                    ->whereIn($column, $ips)
                    ->get(['id', $column])
                    ->each(fn (object $row) => $note((int) $row->id, (string) $row->{$column}));
            }
        }

        $linked = array_map(fn (array $entry): array => ['ips' => array_keys($entry['ips'])] + $entry, array_values($found));

        usort($linked, fn (array $a, array $b): int => [$b['device'], $b['hardware'], count($b['ips']), $b['last_seen'] ?? 0]
            <=> [$a['device'], $a['hardware'], count($a['ips']), $a['last_seen'] ?? 0]);

        return array_slice($linked, 0, self::MAX_LINKED);
    }

    /**
     * @param  list<mixed>  $ips
     *
     * @return list<string>
     */
    private static function publicIps(array $ips): array
    {
        $public = array_filter($ips, fn (mixed $ip): bool => is_string($ip)
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false);

        return array_values(array_unique($public));
    }

    /**
     * "Chrome 141", or with $full "Google Chrome 141.0.7390.108". Client hints
     * name the real browser - Edge, Opera and Brave all say so there - where
     * the user agent mostly says Chrome.
     *
     * @param  array<string, mixed>  $specs
     */
    private static function browser(Agent $agent, array $specs, bool $full): string
    {
        $brands = $specs['uaData']['fullVersionList'] ?? $specs['uaData']['brands'] ?? null;

        if (is_array($brands)) {
            foreach ($brands as $brand) {
                $name = is_array($brand) ? self::str($brand['brand'] ?? null) : null;

                if ($name === null || str_starts_with($name, 'Not') || $name === 'Chromium') {
                    continue;
                }

                $version = (string) self::str($brand['version'] ?? null);

                return trim(($full ? $name : preg_replace('/^Google /', '', $name)) . ' ' . ($full ? $version : self::major($version)));
            }
        }

        $name = $agent->browser();

        if (! is_string($name) || $name === '') {
            return 'Unknown browser';
        }

        $version = $agent->version($name);

        return trim($name . ' ' . (is_string($version) ? ($full ? $version : self::major($version)) : ''));
    }

    /**
     * "Windows 11", "macOS 15.1", "Android 14", "iOS 18.0". Client hints know
     * Windows 11 and the real macOS version, which user agents freeze.
     *
     * @param  array<string, mixed>  $specs
     */
    private static function os(Agent $agent, array $specs): string
    {
        $platform = self::str($specs['uaData']['platform'] ?? null);
        $version = (string) self::str($specs['uaData']['platformVersion'] ?? null);

        if ($platform === 'Windows' && $version !== '') {
            $major = (int) $version;

            return $major >= 13 ? 'Windows 11' : ($major > 0 ? 'Windows 10' : 'Windows 7 or 8');
        }

        if ($platform !== null && $platform !== 'Unknown' && $version !== '') {
            return $platform . ' ' . preg_replace('/\.0$/', '', implode('.', array_slice(explode('.', $version), 0, 2)));
        }

        $name = $agent->platform();

        if (! is_string($name) || $name === '') {
            return $platform ?? 'Unknown system';
        }

        $version = $agent->version($name);
        $version = is_string($version) ? str_replace('_', '.', $version) : '';
        $name = match ($name) {
            'OS X' => 'macOS',
            'AndroidOS' => 'Android',
            default => $name,
        };

        if ($name === 'Windows' && str_starts_with($version, '10')) {
            return 'Windows 10 or 11';
        }

        return trim($name . ' ' . $version);
    }

    /** @param  array<string, mixed>  $specs */
    private static function deviceType(Agent $agent, array $specs): string
    {
        if ($agent->isRobot()) {
            return 'Bot';
        }

        // iPadOS Safari calls itself a Mac; a Mac has no touch screen.
        if (self::str($specs['platform'] ?? null) === 'MacIntel' && (int) ($specs['touch'] ?? 0) > 1) {
            return 'Tablet (iPad)';
        }

        if ($agent->isTablet()) {
            return 'Tablet';
        }

        if ($agent->isMobile() || ($specs['uaData']['mobile'] ?? false) === true) {
            return 'Phone';
        }

        return 'Computer';
    }

    /** @param  array<string, mixed>  $specs */
    private static function screen(array $specs): ?string
    {
        $screen = is_array($specs['screen'] ?? null) ? $specs['screen'] : [];

        if (! isset($screen['w'], $screen['h'])) {
            return null;
        }

        $dpr = is_numeric($screen['dpr'] ?? null) ? round((float) $screen['dpr'], 2) : 1.0;

        return "{$screen['w']}×{$screen['h']}" . ($dpr !== 1.0 ? " @{$dpr}x" : '');
    }

    /**
     * "NVIDIA GeForce RTX 3060" out of what WebGL hands back, e.g.
     * "ANGLE (NVIDIA, NVIDIA GeForce RTX 3060 (0x00002504) Direct3D11 vs_5_0 ps_5_0, D3D11)"
     * or "ANGLE (Apple, ANGLE Metal Renderer: Apple M2, Unspecified Version)".
     */
    private static function gpuName(?string $renderer): ?string
    {
        if ($renderer === null) {
            return null;
        }

        $name = preg_match('/^ANGLE \([^,]*,\s*(.+?),\s*[^,]*\)$/', $renderer, $match) === 1 ? $match[1] : $renderer;
        $name = preg_replace(['/^ANGLE Metal Renderer:\s*/i', '/\s*\(0x[0-9a-f]+\)/i', '/\s+(Direct3D|OpenGL|Vulkan|vs_\d).*$/i'], '', $name);

        return trim((string) $name) !== '' ? trim((string) $name) : $renderer;
    }

    /**
     * Everything known about one row, as label and value, for the expanded
     * view. Only what was actually reported is listed.
     *
     * @param  array<string, mixed>  $specs
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function details(object $row, array $specs, Agent $agent, ?string $gpu, ?string $character): array
    {
        $ua = is_array($specs['uaData'] ?? null) ? $specs['uaData'] : [];
        $screen = is_array($specs['screen'] ?? null) ? $specs['screen'] : [];
        $viewport = is_array($specs['viewport'] ?? null) ? $specs['viewport'] : [];
        $connection = is_array($specs['connection'] ?? null) ? $specs['connection'] : [];
        $battery = is_array($specs['battery'] ?? null) ? $specs['battery'] : [];
        $media = is_array($specs['media'] ?? null) ? $specs['media'] : [];
        $memory = is_numeric($specs['memory'] ?? null) ? (float) $specs['memory'] : null;
        $offset = is_numeric($specs['tzOffset'] ?? null) ? -(int) $specs['tzOffset'] : null;
        $languages = is_array($specs['languages'] ?? null) ? array_filter($specs['languages'], 'is_string') : [];
        $model = self::str($ua['model'] ?? null) ?? (is_string($agent->device()) && ! in_array($agent->device(), ['', 'WebKit', 'Macintosh'], true) ? $agent->device() : null);

        $rows = [
            ['Played as', $character],
            ['Browser', self::browser($agent, $specs, true)],
            ['Operating system', self::os($agent, $specs)],
            ['Device type', self::deviceType($agent, $specs)],
            ['Device model', $model],
            ['CPU architecture', self::join([self::str($ua['architecture'] ?? null), isset($ua['bitness']) ? $ua['bitness'] . '-bit' : null])],
            ['CPU cores', $specs['cores'] ?? null],
            ['Memory (RAM)', $memory !== null ? rtrim(rtrim(number_format($memory, 2), '0'), '.') . ' GB' . ($memory >= 8 ? ' or more' : '') : null],
            ['Graphics card', $gpu],
            ['Graphics vendor', self::str($specs['gpu']['vendor'] ?? null)],
            ['Graphics (as reported)', self::str($specs['gpu']['renderer'] ?? null)],
            ['WebGL', isset($specs['gpu']['webgl']) ? 'WebGL ' . $specs['gpu']['webgl'] . (isset($specs['gpu']['maxTexture']) ? ', textures up to ' . $specs['gpu']['maxTexture'] . 'px' : '') : null],
            ['Screen', isset($screen['w'], $screen['h']) ? "{$screen['w']} × {$screen['h']}" : null],
            ['Usable screen', isset($screen['availW'], $screen['availH']) ? "{$screen['availW']} × {$screen['availH']}" : null],
            ['Pixel ratio', isset($screen['dpr']) ? $screen['dpr'] . 'x' : null],
            ['Colour depth', isset($screen['depth']) ? $screen['depth'] . '-bit' : null],
            ['Colour range', self::str($specs['colorGamut'] ?? null)],
            ['Window size', isset($viewport['w'], $viewport['h']) ? "{$viewport['w']} × {$viewport['h']}" : null],
            ['Screen orientation', self::str($screen['orientation'] ?? null)],
            ['Touch points', $specs['touch'] ?? null],
            ['Timezone', self::join([self::str($specs['timezone'] ?? null), $offset !== null ? sprintf('UTC%+d:%02d', intdiv($offset, 60), abs($offset % 60)) : null])],
            ['Languages', $languages !== [] ? implode(', ', $languages) : self::str($specs['language'] ?? null)],
            ['Connection', self::join([
                self::str($connection['kind'] ?? null),
                self::str($connection['type'] ?? null),
                isset($connection['downlink']) ? $connection['downlink'] . ' Mbps' : null,
                isset($connection['rtt']) ? $connection['rtt'] . ' ms' : null,
                ($connection['saveData'] ?? false) === true ? 'data saver on' : null,
            ])],
            ['Battery', isset($battery['level']) ? $battery['level'] . '%' . (($battery['charging'] ?? false) === true ? ', charging' : '') : null],
            ['Storage space for sites', isset($specs['storageGB']) ? $specs['storageGB'] . ' GB' : null],
            ['Cameras, mics, speakers', $media !== [] ? sprintf('%d, %d, %d', $media['cameras'] ?? 0, $media['microphones'] ?? 0, $media['speakers'] ?? 0) : null],
            ['Dark mode', isset($specs['colorScheme']) ? ($specs['colorScheme'] === 'dark') : null],
            ['Cookies on', $specs['cookies'] ?? null],
            ['Do Not Track', isset($specs['dnt']) ? in_array((string) $specs['dnt'], ['1', 'yes'], true) : null],
            ['Automated browser', $specs['webdriver'] ?? null],
            ['Country', $row->country],
            ['Location', $row->location],
            ['Device ID', (string) $row->device_id],
            ['Hardware code', $row->hardware_hash],
            ['User agent', $row->user_agent],
            ['Accept-Language', $row->accept_language],
        ];

        $out = [];

        foreach ($rows as [$label, $value]) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            $out[] = [$label, is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value];
        }

        return $out;
    }

    /** @param  list<mixed>  $parts */
    private static function join(array $parts): ?string
    {
        $parts = array_filter($parts, fn (mixed $part): bool => is_scalar($part) && (string) $part !== '');

        return $parts === [] ? null : implode(', ', array_map('strval', $parts));
    }

    private static function major(string $version): string
    {
        return explode('.', $version)[0];
    }

    private static function str(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
