{{--
    The guest frame: the city behind a single card.

    The backdrop is the same reception image the game shows on its hotel
    view (pixelrp_city.png), so the website and the city open on one scene.
    The chip up top still follows the real San Francisco (CityWeather).
--}}
@props(['wide' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="system">

<head>
    <x-prp.document-head />
</head>

<body>
    <div class="prp auth">
        <div class="auth-bg" aria-hidden="true" style="background-image: url('{{ asset('assets/images/pixelrp/city.webp') }}')"></div>

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
            <span></span>

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
