<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ setting('hotel_name') }} — @stack('title', 'Pixel Tower')</title>
    <link rel="icon" type="image/gif" sizes="18x17" href="{{ asset('assets/images/home_icon.gif') }}">

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
