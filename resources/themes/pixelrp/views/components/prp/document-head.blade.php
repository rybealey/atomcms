<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<x-seo-meta />

<link rel="icon" href="{{ asset('favicon.ico') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">

{{-- Geist for text, Geist Mono for numbers: the design system's two faces. --}}
<link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@500&display=swap">

{{-- The theme choice is applied before first paint so a dark page never
     flashes light. "system" follows prefers-color-scheme in the CSS. --}}
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

<x-turnstile.scripts />

@if (setting('google_recaptcha_enabled'))
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif

@stack('scripts')
