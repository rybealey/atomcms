<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ setting('hotel_name') }} — @stack('title', 'PixelRP')</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/themes/' . setting('theme', 'atom') . '/css/app.css', 'resources/themes/' . setting('theme', 'atom') . '/js/app.js'], 'build')
    <x-turnstile.scripts />
    @stack('scripts')
</head>
<body class="flex min-h-screen flex-col">
    <x-site-header cta="play" />

    <main class="flex-1">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-12 px-6 py-12 lg:flex-row lg:items-start lg:py-16">
            {{ $hero }}

            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </div>
    </main>

    <x-footer />

    @if (setting('google_recaptcha_enabled'))
        @push('javascript')
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        @endpush
    @endif

    @stack('javascript')
</body>
</html>
