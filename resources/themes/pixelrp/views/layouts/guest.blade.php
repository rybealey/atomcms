{{--
    The guest frame: the city behind a single card.

    The backdrop is the same reception image the game shows on its hotel
    view (pixelrp_city.png), so the website and the city open on one scene,
    with players who are in the city right now walking its foreground road.
    The chip up top still follows the real San Francisco (CityWeather).
--}}
@props(['wide' => false])

@php
    $walkers = app(\App\Services\City\Sidewalk::class)->walkers();
    $sky = app(\App\Services\City\CityWeather::class)->current();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="system">

<head>
    <x-prp.document-head />
</head>

<body>
    <div class="prp auth">
        <div class="auth-bg" aria-hidden="true" style="background-image: url('{{ asset('assets/images/pixelrp/city.webp') }}')"></div>
        <x-prp.sidewalk :walkers="$walkers" />

        <header class="auth-top">
            <div class="row wrap g2" style="margin-left: auto">
                <span class="chip">
                    <span class="prp-dot prp-dot-online"></span>
                    <span class="t-num">{{ number_format(players_online()) }}</span> {{ __('online') }}
                </span>
                <span class="chip hide-sm">
                    <x-prp.icon name="pin" class="ico-sm" />
                    <span>San Francisco</span><span class="muted">·</span>
                    <span class="t-num" data-sf-time>{{ $sky->time }}</span><span class="muted">·</span>
                    <span>{{ $sky->weatherLabel() }}@if ($sky->temperature !== null), <span class="t-num">{{ $sky->temperature }}°F</span>@endif</span>
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
            <span></span>

            <p class="auth-foot-note">
                {{ __(':hotel is a not-for-profit educational project.', ['hotel' => setting('hotel_name')]) }}
                <a class="focusable" href="{{ route('help-center.rules.index') }}">{{ __('Terms') }}</a> ·
                <a class="focusable" href="{{ route('help-center.rules.index') }}#privacy">{{ __('Privacy') }}</a>
            </p>
        </footer>
    </div>

    @stack('javascript')
</body>

</html>
