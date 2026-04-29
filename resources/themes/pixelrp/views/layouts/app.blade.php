<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ setting('hotel_name') }} — @stack('title', 'Home')</title>
    <link rel="icon" type="image/gif" sizes="18x17" href="{{ asset('assets/images/home_icon.gif') }}">

    @vite(['resources/themes/' . setting('theme', 'atom') . '/css/app.css', 'resources/themes/' . setting('theme', 'atom') . '/js/app.js'], 'build')
    @stack('scripts')
</head>
<body class="flex min-h-screen flex-col">
    <x-messages.flash-messages />

    <x-site-header cta="enter" />

    <nav class="w-full bg-(--color-ink-soft) border-b border-[#2A2A2A]">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-8">
            <div class="flex items-center gap-2 py-2.5">
                <a href="{{ route('me.show') }}" class="pt-subnav-link" aria-current="page">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
                    {{ __('Home') }}
                </a>
                <a href="#" class="pt-subnav-link">{{ __('Corporations') }}</a>
                <a href="#" class="pt-subnav-link">{{ __('Gangs') }}</a>
                <a href="{{ route('leaderboard.index') }}" class="pt-subnav-link">{{ __('Leaderboard') }}</a>
            </div>

            <div class="flex items-center gap-2.5 py-2.5">
                <a href="{{ route('help-center.rules.index') }}" class="pt-subnav-link">{{ __('Guidelines') }}</a>
                <a href="{{ route('profile.show', ['user' => Auth::user()->username]) }}" class="pt-icon-btn" title="{{ __('Profile') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>
                </a>
                <a href="{{ route('settings.account.show') }}" class="pt-icon-btn" title="{{ __('Settings') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </a>
            </div>
        </div>
    </nav>

    <main class="flex-1">
        <div class="mx-auto max-w-7xl px-6 py-8">
            {{ $slot }}
        </div>
    </main>

    <x-footer />

    @stack('javascript')
</body>
</html>
