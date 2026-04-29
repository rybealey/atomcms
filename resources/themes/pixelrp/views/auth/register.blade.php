{{-- Pixel Tower registration: two visual steps as one form, advanced via Alpine. --}}
@php
    $startStep = $errors->hasAny(['terms', 'beta_code']) ? 2 : 1;
    $requiresBeta = (bool) setting('requires_beta_code');
@endphp

<x-guest-layout>
    @push('title', __('Create account'))

    <x-slot:hero>
        <x-feature-checklist
            heading="{{ __('Create your account') }}"
            sub="{{ __('Tell us about yourself. We\'ll ask for your beta key on the next step.') }}"
        />
    </x-slot:hero>

    <div x-data="pixelrpRegister({{ $startStep }})" class="pt-card w-full max-w-md">
        <div class="pt-card-header">
            <h2>{{ __('Register') }}</h2>
            <p>
                <span x-show="step === 1">{{ __('Step 1 of 2 — Account details') }}</span>
                <span x-show="step === 2" x-cloak>{{ __('Step 2 of 2 — Verify access') }}</span>
            </p>
        </div>

        <div class="pt-card-body">
            <form method="POST" action="{{ route('register') }}" id="pixelrp-register-form" @submit="onSubmit($event)" class="flex flex-col gap-4">
                @csrf

                <input type="hidden" name="referral_code" value="{{ $referral_code ?? '' }}">

                {{-- Step 1 fields --}}
                <div x-show="step === 1" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <label for="username" class="pt-section-label text-(--color-ink)">{{ __('Username') }}</label>
                        <input id="username" name="username" type="text" value="{{ old('username') }}"
                            placeholder="PixelKnight92" autocomplete="username" autofocus
                            class="pt-input @error('username') border-(--color-danger) @enderror">
                        @error('username')<p class="pt-input-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="mail" class="pt-section-label text-(--color-ink)">{{ __('Email') }}</label>
                        <input id="mail" name="mail" type="email" value="{{ old('mail') }}"
                            placeholder="you@pixeltower.gg" autocomplete="email"
                            class="pt-input @error('mail') border-(--color-danger) @enderror">
                        @error('mail')<p class="pt-input-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col gap-2" x-data="{ show: false }">
                        <label for="password" class="pt-section-label text-(--color-ink)">{{ __('Password') }}</label>
                        <div class="relative">
                            <input id="password" name="password" :type="show ? 'text' : 'password'"
                                placeholder="••••••••••" autocomplete="new-password"
                                class="pt-input pr-12 @error('password') border-(--color-danger) @enderror">
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-(--color-ink)/70 hover:text-(--color-ink)" tabindex="-1">
                                <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        @error('password')<p class="pt-input-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col gap-2" x-data="{ show: false }">
                        <label for="password_confirmation" class="pt-section-label text-(--color-ink)">{{ __('Confirm password') }}</label>
                        <div class="relative">
                            <input id="password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'"
                                placeholder="••••••••••" autocomplete="new-password"
                                class="pt-input pr-12">
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-(--color-ink)/70 hover:text-(--color-ink)" tabindex="-1">
                                <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <p x-show="step1Error" x-text="step1Error" x-cloak class="pt-input-error"></p>
                    </div>

                    <button type="button" @click="goToStep2()" class="pt-btn pt-btn--primary pt-btn--block mt-2">
                        {{ __('Next') }} →
                    </button>
                </div>

                {{-- Step 2 fields (mounted always so a single POST submits all values; visibility controlled via x-show) --}}
                <div x-show="step === 2" x-cloak class="flex flex-col gap-4">
                    @if ($requiresBeta)
                        <div class="flex flex-col gap-2">
                            <label for="beta_code" class="pt-section-label text-(--color-ink)">{{ __('Beta key') }}</label>
                            <input id="beta_code" name="beta_code" type="text" value="{{ old('beta_code') }}"
                                placeholder="PXLTWR-BETA-XXXXX"
                                class="pt-input pt-input--coin @error('beta_code') border-(--color-danger) @enderror">
                            @error('beta_code')<p class="pt-input-error">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <label class="flex items-start gap-3 text-[13px] font-semibold text-(--color-ink) cursor-pointer">
                        <input type="checkbox" name="terms" x-model="terms" class="pt-checkbox mt-0.5">
                        <span>
                            {{ __('I agree to') }} <a href="{{ route('help-center.rules.index') }}" target="_blank" class="pt-link">{{ __('Pixel Tower Digital\'s Terms of Service and Privacy Policy') }}</a>.
                        </span>
                    </label>
                    @error('terms')<p class="pt-input-error">{{ $message }}</p>@enderror

                    <label class="flex items-start gap-3 text-[13px] font-semibold text-(--color-ink) cursor-pointer">
                        <input type="checkbox" x-model="affiliation" class="pt-checkbox mt-0.5">
                        <span>{{ __('I understand that Pixel Tower Digital is in no way affiliated with Sulake Corporation Oy or its subsidiaries.') }}</span>
                    </label>
                    <p x-show="step2Error" x-text="step2Error" x-cloak class="pt-input-error"></p>

                    @if (setting('google_recaptcha_enabled'))
                        <div class="g-recaptcha" data-sitekey="{{ config('habbo.site.recaptcha_site_key') }}"></div>
                    @endif

                    @if (setting('cloudflare_turnstile_enabled'))
                        <x-turnstile />
                    @endif

                    <button type="submit" class="pt-btn pt-btn--primary pt-btn--block mt-2">
                        {{ __('Create account') }} →
                    </button>
                </div>
            </form>
        </div>

        <div class="pt-card-footer">
            <span x-show="step === 1">
                {{ __('Already have an account?') }}
                <a href="{{ route('welcome') }}" class="pt-link ml-1">{{ __('Sign in') }}</a>
            </span>
            <span x-show="step === 2" x-cloak>
                {{ __('Need to start over?') }}
                <button type="button" @click="step = 1" class="pt-link ml-1">{{ __('Back to step 1') }}</button>
            </span>
        </div>
    </div>

    @push('javascript')
        <script>
            function pixelrpRegister(initialStep) {
                return {
                    step: initialStep,
                    step1Error: '',
                    step2Error: '',
                    terms: {{ old('terms') ? 'true' : 'false' }},
                    affiliation: false,
                    goToStep2() {
                        const f = document.getElementById('pixelrp-register-form');
                        const username = f.username.value.trim();
                        const mail = f.mail.value.trim();
                        const password = f.password.value;
                        const confirm = f.password_confirmation.value;
                        if (!username || !mail || !password) {
                            this.step1Error = '{{ __('All fields are required.') }}';
                            return;
                        }
                        if (password.length < 8) {
                            this.step1Error = '{{ __('Password must be at least 8 characters.') }}';
                            return;
                        }
                        if (password !== confirm) {
                            this.step1Error = '{{ __('Passwords do not match.') }}';
                            return;
                        }
                        this.step1Error = '';
                        this.step = 2;
                    },
                    onSubmit(e) {
                        if (this.step !== 2) {
                            e.preventDefault();
                            return;
                        }
                        if (!this.terms || !this.affiliation) {
                            e.preventDefault();
                            this.step2Error = '{{ __('You must accept both agreements to continue.') }}';
                        }
                    },
                };
            }
        </script>
    @endpush
</x-guest-layout>
