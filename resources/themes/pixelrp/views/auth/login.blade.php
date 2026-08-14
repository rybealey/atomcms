{{--
    Fortify's configured login view. Today `GET /login` is overridden in
    routes/web.php to redirect to the landing page, so this is only reached if
    that redirect is ever removed - it renders the same card either way.
--}}
<x-guest-layout>
    @push('title', __('Log in'))

    <img class="pixel-logo" src="{{ asset('assets/images/pixelrp/logo-animated.gif') }}"
        alt="{{ setting('hotel_name') }}" width="201" height="99">

    <x-pixel.login-card />
</x-guest-layout>
