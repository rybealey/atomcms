<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-seo-meta />

    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">

    {{-- Pixel type. Substitutes picked by the design system: Press Start 2P for
         display, Silkscreen for HUD labels, Barlow for body copy. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Silkscreen:wght@400;700&family=Barlow:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap">

    {{-- The desktop scene is the largest thing on the page and it is the page's
         backdrop, so start it early rather than waiting on the stylesheet. --}}
    <link rel="preload" as="image" href="{{ asset('assets/images/pixelrp/scene-terrace.webp') }}"
        media="(min-width: 768px)">

    @vite(['resources/themes/' . setting('theme', 'pixelrp') . '/css/app.css'], 'build')

    <x-turnstile.scripts />

    @if (setting('google_recaptcha_enabled'))
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
</head>

<body>
    <div class="pixel-scene">
        <div class="pixel-scene__overlay"></div>

        {{-- Fixed seeds, so the sky does not reshuffle on every request. --}}
        <div class="pixel-scene__stars" aria-hidden="true">
            @foreach ([[6, 12, 7.1, 0], [14, 28, 5.3, 1.2], [88, 9, 6.2, 0.4], [93, 31, 4.7, 2.1], [78, 68, 5.9, 0.9], [9, 74, 6.8, 1.6], [96, 55, 5.1, 0.2], [22, 8, 7.4, 2.8], [84, 84, 4.9, 1.1], [4, 46, 6.1, 0.7]] as [$x, $y, $duration, $delay])
                <span class="pixel-star"
                    style="left: {{ $x }}%; top: {{ $y }}%; --star-duration: {{ $duration }}s; --star-delay: {{ $delay }}s"></span>
            @endforeach
        </div>

        <div class="pixel-scene__body">
            {{ $slot }}
        </div>

        {{ $ticker ?? '' }}

        <div class="pixel-footer">
            {{ __(':hotel is a not for profit educational project.', ['hotel' => setting('hotel_name')]) }}
        </div>
    </div>

    @stack('scripts')
</body>

</html>
