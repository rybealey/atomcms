<x-guest-layout aria-labelledby="forgot-title">
    @push('title', __('Forgot password'))

    <div class="stack g2">
        <img class="px-img auth-logo" src="{{ asset('assets/images/pixelrp/logo-hero.gif') }}" width="201" height="55" alt="{{ setting('hotel_name') }}">
        <h1 id="forgot-title" class="t-h1">{{ __('Lost your keys?') }}</h1>
        <p class="t-body muted">{{ __('Tell us your email address and we will send you a link to choose a new password.') }}</p>
    </div>

    <x-prp.alert :handled="['mail']" />

    <form class="stack g4" method="POST" action="{{ route('forgot.password.post') }}">
        @csrf

        <x-prp.field name="mail" type="email" :label="__('Email address')" autocomplete="email" :autofocus="true" />

        <x-prp.captcha />

        <button type="submit" class="prp-btn prp-btn-primary prp-btn-lg prp-btn-block">{{ __('Send reset link') }}</button>
    </form>

    <p class="t-sm muted ta-center">
        {{ __('Remembered it?') }}
        <a class="link" href="{{ route('welcome') }}">{{ __('Log in') }}</a>
    </p>
</x-guest-layout>
