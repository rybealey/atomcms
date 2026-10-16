{{--
    The guest frame: the live San Francisco street behind a single card.

    The sky follows the real city (CityWeather): time of day from the Pacific
    clock, weather from the same Open-Meteo reading the game uses. The people
    on the sidewalk are players who are in the city right now, drawn walking
    by the hotel's own imager.
--}}
@props(['wide' => false])

@php
    $sky = app(\App\Services\City\CityWeather::class)->current();
    $walkers = app(\App\Services\City\Sidewalk::class)->walkers();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="system">

<head>
    <x-prp.document-head />
</head>

<body>
    <div class="prp auth">
        <x-prp.street-scene :sky="$sky" :walkers="$walkers" />

        <header class="auth-top">
            <div class="row wrap g2" style="margin-left: auto">
                <span class="chip">
                    <span class="prp-dot prp-dot-online"></span>
                    <span class="t-num">{{ number_format(players_online()) }}</span> {{ __('online') }}
                </span>
                {{ $top ?? '' }}
            </div>
        </header>

        <main class="auth-center" id="main">
            <section {{ $attributes->class(['auth-card', 'auth-card-wide' => $wide]) }}>
                {{ $slot }}
            </section>
        </main>

        <footer class="auth-foot">
            <button type="button" class="chip chip-btn focusable" aria-pressed="false" data-scene-pause
                data-pause-label="{{ __('Pause street') }}" data-play-label="{{ __('Play street') }}">
                <x-prp.icon name="pause" class="ico-sm" data-scene-icon="pause" />
                <x-prp.icon name="play" class="ico-sm" data-scene-icon="play" hidden />
                <span data-scene-label>{{ __('Pause street') }}</span>
            </button>

            <span class="chip chip-tall">
                <span class="muted">{{ __(':hotel is a not-for-profit educational project.', ['hotel' => setting('hotel_name')]) }}</span>
                <a class="link ink" href="{{ route('help-center.rules.index') }}">{{ __('Terms') }}</a>
                <a class="link ink" href="{{ route('help-center.rules.index') }}#privacy">{{ __('Privacy') }}</a>
            </span>
        </footer>
    </div>

    @stack('javascript')
</body>

</html>
