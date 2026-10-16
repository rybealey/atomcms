{{--
    Reached two ways: Fortify's registerView (plain /register) and
    UserReferralController (/register/{referral_code}). Only $referral_code is
    guaranteed to be present.
--}}
@php
    $looks = config('hotel.starting_looks', []);
    $selectedLook = old('look', array_key_first($looks));
@endphp

<x-guest-layout wide aria-labelledby="reg-title">
    @push('title', __('Create account'))

    <a href="{{ route('welcome') }}" aria-label="{{ __(':hotel, back to log in', ['hotel' => setting('hotel_name')]) }}" class="focusable auth-logo">
        <img class="px-img" src="{{ asset('assets/images/pixelrp/logo-hero.gif') }}" width="201" height="55" alt="">
    </a>

    <div class="stack g2">
        <p class="t-over brand-ink">{{ __('Citizenship application') }}</p>
        <h1 id="reg-title" class="t-display">{{ __('Move to San Francisco') }}</h1>
        <p class="t-body muted">{{ __('Pick a name and choose your vibe.') }}</p>
    </div>

    <x-prp.alert :handled="['username', 'mail', 'password', 'password_confirmation', 'beta_code', 'look', 'terms']" />

    <form class="stack g5" method="POST" action="{{ route('register') }}">
        @csrf

        <div class="form-grid">
            <div class="full">
                <x-prp.field name="username" id="reg-user" :label="__('Username')" autocomplete="username" autocapitalize="none" spellcheck="false"
                    :autofocus="true" :hint="__('3 to 15 characters: letters, numbers, dashes and underscores. This is your name in the city.')" />
            </div>

            <div class="full">
                <x-prp.field name="mail" id="reg-mail" type="email" :label="__('Email')" autocomplete="email"
                    :hint="__('Only used for account recovery and verification for account and billing support where applicable.')" />
            </div>

            <x-prp.field name="password" id="reg-pass" type="password" :label="__('Password')" autocomplete="new-password"
                aria-describedby="reg-pass-hint" />

            <x-prp.field name="password_confirmation" id="reg-pass2" type="password" :label="__('Confirm password')" autocomplete="new-password"
                aria-describedby="reg-pass-hint" />

            <span class="prp-field-hint full" id="reg-pass-hint" style="margin-top: -8px">
                {{ __('At least 12 characters, one capital letter, one lowercase letter, one number and one special character.') }}
            </span>
        </div>

        @if ($looks !== [])
            <fieldset class="stack g2" style="margin: 0; padding: 0; border: 0">
                <legend class="prp-field-label" style="padding: 0; margin-bottom: 8px">{{ __('Starting look') }}</legend>
                <div class="looks">
                    @foreach ($looks as $key => $look)
                        <label class="radio-card">
                            <input type="radio" name="look" value="{{ $key }}" @checked($selectedLook === $key)>
                            <x-prp.figure :look="$look['figure']" size="m" alt="" />
                            <span class="t-label">{{ __($look['label']) }}</span>
                        </label>
                    @endforeach
                </div>
                @error('look')
                    <span class="prp-field-error">{{ $message }}</span>
                @enderror
            </fieldset>
        @endif

        @if (setting('requires_beta_code'))
            <x-prp.field name="beta_code" id="reg-key" :label="__('Early Access Key')" autocomplete="off" autocapitalize="characters" spellcheck="false"
                placeholder="XXXX-XXXX-XXXX" class="mono-input"
                :hint="__(':hotel is in early access. You need a key to create an account.', ['hotel' => setting('hotel_name')])" />
        @endif

        <x-prp.check name="terms" :required="true" :top="true">
            {!! __("I'm 18 or older and I agree to the :terms and the :rules.", [
                'terms' => '<a class="link" href="'.e(route('help-center.rules.index')).'" target="_blank" rel="noopener">'.e(__('Terms')).'</a>',
                'rules' => '<a class="link" href="'.e(route('help-center.rules.index')).'" target="_blank" rel="noopener">'.e(__('city rules')).'</a>',
            ]) !!}
        </x-prp.check>

        {{-- Credits the referrer once the account is created. --}}
        <input type="hidden" name="referral_code" value="{{ $referral_code }}">

        <x-prp.captcha />

        <button type="submit" class="prp-btn prp-btn-primary prp-btn-lg prp-btn-block">{{ __('Create account') }}</button>
    </form>

    <p class="t-sm muted ta-center">
        {{ __('Already a citizen?') }}
        <a class="link" href="{{ route('welcome') }}">{{ __('Log in') }}</a>
    </p>
</x-guest-layout>
