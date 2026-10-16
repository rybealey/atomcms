<x-guest-layout aria-labelledby="login-title">
    @push('title', __('Two-factor authentication'))

    <div class="stack g2">
        <p class="t-over brand-ink">{{ __('Two-factor check') }}</p>
        <h1 id="login-title" class="t-h1">{{ __('Enter your code') }}</h1>
        <p class="t-body muted">{{ __('Open your authenticator app and enter the 6-digit code for :hotel.', ['hotel' => setting('hotel_name')]) }}</p>
    </div>

    <x-prp.alert :handled="['code', 'recovery_code']" />

    <form class="stack g4" method="POST" action="{{ route('two-factor.login') }}">
        @csrf

        <div class="prp-field">
            <label class="prp-field-label" for="login-otp">{{ __('Authentication code') }}</label>
            <input class="prp-input code-input" id="login-otp" name="code" inputmode="numeric" autocomplete="one-time-code"
                maxlength="6" placeholder="000000" aria-describedby="login-otp-hint" autofocus
                @error('code') aria-invalid="true" @enderror>
            @error('code')
                <span class="prp-field-error">{{ $message }}</span>
            @enderror
            <span class="prp-field-hint" id="login-otp-hint">{{ __('Codes refresh every 30 seconds.') }}</span>
        </div>

        <details class="reveal stack g3" @if ($errors->has('recovery_code') || old('recovery_code')) open @endif>
            <summary class="link t-sm">{{ __('Use a recovery code') }}</summary>
            <x-prp.field name="recovery_code" :label="__('Recovery code')" autocomplete="one-time-code" :required="false"
                class="mono-input" :hint="__('One of the codes you saved when you turned on two-factor. Each works once.')" />
        </details>

        <x-prp.captcha />

        <button type="submit" class="prp-btn prp-btn-primary prp-btn-lg prp-btn-block">{{ __('Verify and log in') }}</button>
    </form>

    <div class="row between wrap">
        <span></span>
        <a class="prp-btn prp-btn-quiet prp-btn-sm" href="{{ route('welcome') }}">{{ __('Back to log in') }}</a>
    </div>
</x-guest-layout>
