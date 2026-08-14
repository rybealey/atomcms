<x-guest-layout>
    @push('title', __('Reset password'))

    <img class="pixel-logo pixel-logo--sm" src="{{ asset('assets/images/pixelrp/logo-animated.gif') }}"
        alt="{{ setting('hotel_name') }}" width="201" height="99">

    <div class="pixel-card">
        <div class="pixel-card__body">
            <h1 class="pixel-card__title">{{ __('New password') }}</h1>

            <x-pixel.alert :handled="['password', 'mail']" />

            <form class="flex flex-col gap-4" method="POST" action="{{ route('reset.password.post', $token) }}">
                @csrf

                <div class="flex flex-col gap-3">
                    <x-pixel.input name="password" type="password" :label="__('Password')" placeholder="••••••••"
                        autocomplete="new-password"
                        :hint="__('At least 12 characters, with upper and lower case, a number and a symbol.')" />

                    <x-pixel.input name="password_confirmation" type="password" :label="__('Verify password')"
                        placeholder="••••••••" autocomplete="new-password" />
                </div>

                <x-pixel.button size="lg" :full-width="true">
                    {{ __('▶ Reset password') }}
                </x-pixel.button>
            </form>

            <p class="pixel-meta">
                <a class="pixel-link" href="{{ route('welcome') }}">{{ __('Back to log in') }}</a>
            </p>
        </div>
    </div>
</x-guest-layout>
