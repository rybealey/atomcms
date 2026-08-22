<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ setting('hotel_name') }} - Nitro</title>

    <link href="https://fonts.googleapis.com/css2?family=Ubuntu+Condensed&display=swap" rel="stylesheet">

    {{-- Pixel type for the disconnect overlay (matches the guest auth screens). --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Silkscreen:wght@400;700&display=swap">

    @livewireStyles
    @livewireScriptConfig
    @vite(['resources/themes/' .  setting('theme') . '/css/app.css', 'resources/themes/' .  setting('theme') . '/js/app.js'], 'build')
</head>

<body class="overflow-hidden" id="nitro-client">
    <iframe id="nitro" src="{{ sprintf('%s/index.html?sso=%s', setting('nitro_path'), $sso) }}"
        class="absolute top-0 left-0 m-0 h-full w-full overflow-hidden border-none p-0"></iframe>

    {{-- Show disconnected message on client if the user has been disconnected.
         Styled with the theme's pixel design system (pixel.css) so it matches
         the guest auth screens: cream ink-outlined card, pixel type, CTA +
         ghost buttons. --}}
    <div id="disconnected" class="h-screen w-full">
        <div class="absolute h-full w-full bg-black/60"></div>

        <div class="relative flex h-full w-full flex-col items-center justify-center gap-6">
            <img class="pixel-logo" src="{{ asset('assets/images/pixelrp/logo-animated.gif') }}"
                alt="{{ setting('hotel_name') }}" width="201" height="99">

            <div class="pixel-card">
                <div class="pixel-card__body">
                    <h2 class="pixel-card__title">
                        {{ __('Connection lost') }}
                    </h2>

                    <p class="pixel-card__lede" style="text-align: center;">
                        {{ __('Whoops! It seems like you have been disconnected...') }}
                    </p>

                    <button class="pixel-button pixel-button--full" onclick="reloadClient()">
                        {{ __('Reload client') }}
                    </button>

                    <a href="{{ route('me.show') }}" class="pixel-button pixel-button--ghost pixel-button--full">
                        {{ __('Back to website') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function reloadClient() {
            window.location.href = window.location;
        }
    </script>

    <script src="{{ asset('assets/js/atom.js') }}"></script>
</body>

</html>
