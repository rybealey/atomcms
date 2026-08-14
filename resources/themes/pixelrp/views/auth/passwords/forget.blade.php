<x-guest-layout>
    @push('title', __('Forgot password'))

    <img class="pixel-logo pixel-logo--sm" src="{{ asset('assets/images/pixelrp/logo-animated.gif') }}"
        alt="{{ setting('hotel_name') }}" width="201" height="99">

    <div class="pixel-card">
        <div class="pixel-card__body">
            <h1 class="pixel-card__title">{{ __('Lost your keys?') }}</h1>

            <p class="pixel-card__lede">
                {{ __('Tell us your email address and we will send you a link to choose a new password.') }}
            </p>

            <x-pixel.alert :handled="['mail']" />

            <form class="flex flex-col gap-4" method="POST" action="{{ route('forgot.password.post') }}">
                @csrf

                <x-pixel.input name="mail" type="email" :label="__('Email address')" placeholder="you@example.com"
                    autocomplete="email" :autofocus="true" />

                <x-pixel.button size="lg" :full-width="true">
                    {{ __('▶ Send reset link') }}
                </x-pixel.button>
            </form>

            <p class="pixel-meta">
                {{ __('Remembered it?') }}
                <a class="pixel-link" href="{{ route('welcome') }}">{{ __('Log in') }}</a>
            </p>
        </div>
    </div>
</x-guest-layout>
