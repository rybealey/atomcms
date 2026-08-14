<x-guest-layout>
    @push('title', __('Two-factor authentication'))

    <img class="pixel-logo pixel-logo--sm" src="{{ asset('assets/images/pixelrp/logo-animated.gif') }}"
        alt="{{ setting('hotel_name') }}" width="201" height="99">

    <div class="pixel-card">
        <div class="pixel-card__body">
            <h1 class="pixel-card__title">{{ __('Security check') }}</h1>

            <p class="pixel-card__lede">
                {{ __('Enter the code from your authenticator app, or one of your recovery codes.') }}
            </p>

            <x-pixel.alert :handled="['code', 'recovery_code']" />

            <form class="flex flex-col gap-4" method="POST" action="{{ route('two-factor.login') }}">
                @csrf

                <div class="flex flex-col gap-3">
                    <x-pixel.input name="code" :label="__('Authentication code')" placeholder="000000"
                        autocomplete="one-time-code" inputmode="numeric" :required="false" :autofocus="true" />

                    <x-pixel.input name="recovery_code" :label="__('Recovery code')"
                        :placeholder="__('Use a recovery code instead')" autocomplete="one-time-code"
                        :required="false" />
                </div>

                <x-pixel.captcha />

                <x-pixel.button size="lg" :full-width="true">
                    {{ __('▶ Verify') }}
                </x-pixel.button>
            </form>
        </div>
    </div>
</x-guest-layout>
