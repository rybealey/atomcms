{{--
    Log in. Served at `/` (the landing page) and as Fortify's login view, so the
    two can never drift apart.
--}}
<x-guest-layout aria-labelledby="login-title">
    @push('title', __('Log in'))

    <div class="stack g2">
        <img class="px-img auth-logo" src="{{ asset('assets/images/pixelrp/logo-hero.gif') }}" width="201" height="55" alt="{{ setting('hotel_name') }}">
        <h1 id="login-title" class="t-h1">{{ __('Welcome back') }}</h1>
        <p class="t-body muted">{{ __('Log in to step back onto the streets of :hotel.', ['hotel' => setting('hotel_name')]) }}</p>
    </div>

    @if ($errors->has('username') || $errors->has('password'))
        <div class="prp-alert prp-alert-danger" role="alert">
            <x-prp.icon name="alert-circle" class="prp-alert-icon" />
            <div>
                <span class="prp-alert-title">{{ __("Couldn't log you in") }}</span>
                {{ $errors->first('username') ?: $errors->first('password') }}
            </div>
        </div>
    @endif

    <x-prp.alert :handled="['username', 'password']" />

    <form class="stack g4" method="POST" action="{{ route('login') }}">
        @csrf

        <div class="prp-field">
            <label class="prp-field-label" for="login-user">{{ __('Username') }}</label>
            <input class="prp-input" id="login-user" name="username" value="{{ old('username') }}" autocomplete="username"
                autocapitalize="none" spellcheck="false" required autofocus
                @if ($errors->has('username') || $errors->has('password')) aria-invalid="true" @endif>
        </div>

        <div class="prp-field">
            <label class="prp-field-label" for="login-pass">{{ __('Password') }}</label>
            <div class="pw">
                <input class="prp-input" id="login-pass" name="password" type="password" autocomplete="current-password" required
                    @if ($errors->has('username') || $errors->has('password')) aria-invalid="true" @endif>
                <button type="button" class="prp-btn prp-btn-quiet prp-btn-sm" aria-controls="login-pass" aria-pressed="false"
                    data-pw-toggle data-show-label="{{ __('Show') }}" data-hide-label="{{ __('Hide') }}">{{ __('Show') }}</button>
            </div>
        </div>

        <div class="row between wrap">
            <x-prp.check name="remember" :checked="true">{{ __('Keep me logged in') }}</x-prp.check>
            <a class="link t-sm" href="{{ route('forgot.password.get') }}">{{ __('Forgot password?') }}</a>
        </div>

        <x-prp.captcha />

        <button type="submit" class="prp-btn prp-btn-primary prp-btn-lg prp-btn-block">{{ __('Log in') }}</button>
    </form>

    @if (setting('disable_registration') !== '1')
        <div class="auth-or">{{ __('New to San Francisco?') }}</div>
        <a class="prp-btn prp-btn-ghost prp-btn-lg prp-btn-block" href="{{ route('register') }}">{{ __('Create an account') }}</a>
    @endif
</x-guest-layout>
