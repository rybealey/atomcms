<x-guest-layout aria-labelledby="reset-title">
    @push('title', __('Reset password'))

    <div class="stack g2">
        <img class="px-img auth-logo" src="{{ asset('assets/images/pixelrp/logo-hero.gif') }}" width="201" height="55" alt="{{ setting('hotel_name') }}">
        <h1 id="reset-title" class="t-h1">{{ __('Choose a new password') }}</h1>
        <p class="t-body muted">{{ __('Make it one you do not use anywhere else.') }}</p>
    </div>

    <x-prp.alert :handled="['password', 'password_confirmation', 'mail']" />

    <form class="stack g4" method="POST" action="{{ route('reset.password.post', $token) }}">
        @csrf

        <x-prp.field name="password" type="password" :label="__('New password')" autocomplete="new-password" :autofocus="true"
            :hint="__('At least 12 characters, one capital letter, one lowercase letter, one number and one special character.')" />

        <x-prp.field name="password_confirmation" type="password" :label="__('Confirm new password')" autocomplete="new-password" />

        <button type="submit" class="prp-btn prp-btn-primary prp-btn-lg prp-btn-block">{{ __('Reset password') }}</button>
    </form>

    <p class="t-sm muted ta-center">
        <a class="link" href="{{ route('welcome') }}">{{ __('Back to log in') }}</a>
    </p>
</x-guest-layout>
