{{--
    The setup wizard's frame. It is a default view rather than a theme view
    on purpose: the wizard runs before a theme is settled (restarting it puts
    the theme setting back to atom), so nothing here may depend on a theme's
    components. It loads the PixelRP stylesheet directly and uses only its
    plain classes.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="system">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ setting('hotel_name') }} - @stack('title')</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@500&display=swap">

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('prp-theme');
                if (theme === 'light' || theme === 'dark' || theme === 'system') {
                    document.documentElement.setAttribute('data-theme', theme);
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/themes/pixelrp/css/app.css', 'resources/themes/pixelrp/js/app.js'], 'build')
    @stack('scripts')
</head>

<body>
    <div class="prp pg">
        <a class="skip" href="#main">{{ __('Skip to content') }}</a>

        <header class="an">
            <div class="prp-nav an-in">
                <span class="wm prp-nav-brand">
                    <img class="an-wm" src="{{ asset('assets/images/pixelrp/wordmark.png') }}" width="117" height="32" alt="{{ setting('hotel_name') }}">
                    <img class="an-em" src="{{ asset('assets/images/pixelrp/emblem.png') }}" width="18" height="32" alt="{{ setting('hotel_name') }}">
                </span>

                <div class="prp-nav-end">
                    <span class="chip">
                        <span class="prp-dot prp-dot-online"></span>
                        {{ __('Setup') }}
                    </span>
                </div>
            </div>
        </header>

        <main id="main" class="pg-main" style="max-width: 760px">
            {{ $slot }}
        </main>

        <footer class="pg-foot">
            <div class="pg-foot-in">
                <span>{{ __('Atom CMS setup') }}</span>
                <nav aria-label="{{ __('Footer') }}">
                    <a href="https://github.com/atom-retros/atomcms/wiki" target="_blank" rel="noopener">{{ __('Documentation') }}</a>
                    <a href="https://discord.gg/pP6HyZedAj" target="_blank" rel="noopener">Discord</a>
                </nav>
            </div>
        </footer>
    </div>

    @stack('javascript')
</body>

</html>
