<?php

namespace App\Services\City;

use App\Data\CitySky;
use App\Support\OutboundHttp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The website's copy of the sky the game shows.
 *
 * The emulator's WeatherStation pulls the real San Francisco from Open-Meteo
 * every ten minutes and pushes it to every phone; this reads the same source
 * on the same cadence so a foggy night on the login page is a foggy night in
 * the city. Staff can hold the weather or pin the time of day from the City
 * Panel (server_settings city.weather.* / city.time.*), and those overrides
 * are honoured here too, so the website never contradicts the game.
 */
class CityWeather
{
    private const URL = 'https://api.open-meteo.com/v1/forecast?latitude=37.7749&longitude=-122.4194'
        . '&current=temperature_2m,is_day,weather_code&temperature_unit=fahrenheit&timezone=America%2FLos_Angeles';

    private const TIMEZONE = 'America/Los_Angeles';

    public function current(): CitySky
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $reading = $this->reading();
        $override = $this->overrides();

        $minutes = $override['minutes'] ?? ($now->hour * 60 + $now->minute);
        $code = $override['code'] ?? $reading['code'];

        return new CitySky(
            phase: $this->phase($minutes),
            weather: $this->weather($code),
            temperature: $reading['temperature'],
            time: $now->format('g:i A'),
            held: $override['code'] !== null || $override['minutes'] !== null,
        );
    }

    /** @return 'dawn'|'day'|'dusk'|'night' */
    public function phase(int $minutes): string
    {
        return match (true) {
            $minutes >= 6 * 60 && $minutes < 8 * 60 => 'dawn',
            $minutes >= 8 * 60 && $minutes < 17 * 60 => 'day',
            $minutes >= 17 * 60 && $minutes < 20 * 60 => 'dusk',
            default => 'night',
        };
    }

    /**
     * WMO weather code (what Open-Meteo sends) to one of the scene's four skies.
     *
     * @return 'clear'|'cloudy'|'fog'|'rain'
     */
    public function weather(?int $code): string
    {
        return match (true) {
            $code === null, $code <= 1 => 'clear',
            $code <= 3 => 'cloudy',
            $code === 45, $code === 48 => 'fog',
            $code >= 51 => 'rain',
            default => 'cloudy',
        };
    }

    /** @return array{code: int|null, temperature: int|null} */
    private function reading(): array
    {
        /** @var array{code: int|null, temperature: int|null} $reading */
        $reading = Cache::remember('city.weather', now()->addMinutes(10), function (): array {
            try {
                $response = OutboundHttp::request()->get(self::URL);

                if ($response->successful()) {
                    $current = $response->json('current');

                    if (is_array($current)) {
                        $reading = [
                            'code' => isset($current['weather_code']) ? (int) $current['weather_code'] : null,
                            'temperature' => isset($current['temperature_2m']) ? (int) round((float) $current['temperature_2m']) : null,
                        ];

                        // The last good reading outlives the refresh window, so a
                        // blip at Open-Meteo shows yesterday's sky rather than none.
                        Cache::put('city.weather.last-good', $reading, now()->addDay());

                        return $reading;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('City weather refresh failed: ' . $e->getMessage());
            }

            /** @var array{code: int|null, temperature: int|null} */
            return Cache::get('city.weather.last-good', ['code' => null, 'temperature' => null]);
        });

        return $reading;
    }

    /**
     * The City Panel's holds, straight from the emulator's settings table.
     *
     * @return array{code: int|null, minutes: int|null}
     */
    private function overrides(): array
    {
        $none = ['code' => null, 'minutes' => null];

        try {
            $hasTable = Cache::remember('city.has-server-settings', now()->addMinutes(10), fn (): bool => Schema::hasTable('server_settings'));

            if (! $hasTable) {
                return $none;
            }

            /** @var array<string, string> $settings */
            $settings = DB::table('server_settings')
                ->whereIn('key', ['city.weather.pinned', 'city.weather.code', 'city.time.pinned', 'city.time.minutes'])
                ->pluck('value', 'key')
                ->map(fn (mixed $value): string => (string) $value)
                ->all();

            return [
                'code' => ($settings['city.weather.pinned'] ?? '0') === '1' && is_numeric($settings['city.weather.code'] ?? null)
                    ? (int) $settings['city.weather.code']
                    : null,
                'minutes' => ($settings['city.time.pinned'] ?? '0') === '1' && is_numeric($settings['city.time.minutes'] ?? null)
                    ? max(0, min(1439, (int) $settings['city.time.minutes']))
                    : null,
            ];
        } catch (Throwable) {
            return $none;
        }
    }
}
