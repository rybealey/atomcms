<?php

namespace App\Data;

/**
 * What the sky over San Francisco looks like right now, reduced to the four
 * phases and four weathers the street scene can draw.
 */
final class CitySky
{
    /**
     * @param  'dawn'|'day'|'dusk'|'night'  $phase
     * @param  'clear'|'cloudy'|'fog'|'rain'  $weather
     */
    public function __construct(
        public readonly string $phase,
        public readonly string $weather,
        public readonly ?int $temperature,
        public readonly string $time,
        public readonly bool $held = false,
    ) {}

    public function weatherLabel(): string
    {
        return match ($this->weather) {
            'cloudy' => __('Cloudy'),
            'fog' => __('Foggy'),
            'rain' => __('Rain'),
            default => __('Clear'),
        };
    }

    public function greeting(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour < 12 => __('Good morning'),
            $hour >= 12 && $hour < 18 => __('Good afternoon'),
            default => __('Good evening'),
        };
    }
}
