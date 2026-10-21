<?php

namespace App\Support\Housekeeping;

use App\Services\Roleplay\RoleplaySchema;
use Illuminate\Support\Facades\DB;

/**
 * The world switches the City Panel keeps in server_settings (CityWorld,
 * WeatherStation): maintenance, the combat pause, a pinned sky and a pinned
 * clock. Read-only here; they change in game.
 */
final class CityState
{
    /** Open-Meteo / WMO weather codes, as WeatherStation reads them. */
    public static function weatherName(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear',
            $code <= 2 => 'Partly cloudy',
            $code === 3 => 'Overcast',
            $code === 45 || $code === 48 => 'Fog',
            $code >= 51 && $code <= 57 => 'Drizzle',
            $code >= 61 && $code <= 67 => 'Rain',
            $code >= 71 && $code <= 77 => 'Snow',
            $code >= 80 && $code <= 82 => 'Showers',
            $code === 85 || $code === 86 => 'Snow showers',
            $code >= 95 => 'Thunderstorm',
            default => 'Code ' . $code,
        };
    }

    /**
     * @return array{maintenance: bool, combat_paused: bool, weather: array{pinned: bool, code: int|null, name: string|null}, time: array{pinned: bool, minutes: int|null, label: string, phase: string}}
     */
    public static function current(): array
    {
        $settings = RoleplaySchema::has('server_settings')
            ? DB::table('server_settings')
                ->whereIn('key', ['hotel.maintenance', 'hotel.combat_paused', 'city.weather.pinned', 'city.weather.code', 'city.time.pinned', 'city.time.minutes'])
                ->pluck('value', 'key')
                ->map(fn (mixed $v): string => (string) $v)
                ->all()
            : [];

        $weatherPinned = ($settings['city.weather.pinned'] ?? '0') === '1';
        $code = $weatherPinned && is_numeric($settings['city.weather.code'] ?? null) ? (int) $settings['city.weather.code'] : null;
        $timePinned = ($settings['city.time.pinned'] ?? '0') === '1';
        $minutes = $timePinned && is_numeric($settings['city.time.minutes'] ?? null) ? (int) $settings['city.time.minutes'] : null;
        // GameClock: a whole in-game day passes in 90 real minutes, worked out
        // from the real clock unless staff pinned it.
        $cycleMs = 90 * 60 * 1000;
        $clock = $minutes ?? intdiv((int) (floor(microtime(true) * 1000)) % $cycleMs * 1440, $cycleMs);

        return [
            'maintenance' => ($settings['hotel.maintenance'] ?? '0') === '1',
            'combat_paused' => ($settings['hotel.combat_paused'] ?? '0') === '1',
            'weather' => ['pinned' => $weatherPinned, 'code' => $code, 'name' => $code !== null ? self::weatherName($code) : null],
            'time' => [
                'pinned' => $timePinned,
                'minutes' => $minutes,
                'label' => sprintf('%d:%02d %s', ((intdiv($clock, 60) + 11) % 12) + 1, $clock % 60, $clock >= 720 ? 'PM' : 'AM'),
                // GameClock.IsDay: 7 AM up to 7 PM.
                'phase' => $clock >= 420 && $clock < 1140 ? 'Day' : 'Night',
            ],
        ];
    }
}
