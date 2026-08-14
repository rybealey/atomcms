{{--
    The landing page for logged-out visitors. `/` is inside the `guest`
    middleware group, so anyone already signed in is redirected to their home
    before this ever renders - which is why the landing can be the login screen
    itself rather than a marketing page with a login box bolted on.
--}}
<x-guest-layout>
    @push('title', __('Log in'))

    @php
        // This is the busiest guest page on the site, so the ticker reads from a
        // short-lived cache rather than counting rows on every hit.
        $playersOnline = \Illuminate\Support\Facades\Cache::remember(
            'landing.players-online',
            now()->addSeconds(30),
            fn (): int => \App\Models\User::where('online', '1')->count(),
        );
    @endphp

    <img class="pixel-logo" src="{{ asset('assets/images/pixelrp/logo-animated.gif') }}"
        alt="{{ setting('hotel_name') }}" width="201" height="99">

    <x-pixel.login-card />

    <x-slot:ticker>
        <div class="pixel-ticker">
            <span class="pixel-ticker__dot" aria-hidden="true"></span>
            <span>{{ __(':count players online', ['count' => number_format($playersOnline)]) }}</span>
        </div>
    </x-slot:ticker>
</x-guest-layout>
