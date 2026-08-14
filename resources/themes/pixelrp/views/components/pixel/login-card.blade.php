{{-- Shared by the landing page and Fortify's login view so the two can never
     drift apart, whichever one an install ends up serving. --}}
<div class="pixel-card">
    <div class="pixel-card__body">
        <h1 class="pixel-card__title">{{ __('Enter the city') }}</h1>

        <x-pixel.alert :handled="['username', 'password']" />

        <form class="flex flex-col gap-4" method="POST" action="{{ route('login') }}">
            @csrf

            <div class="flex flex-col gap-3">
                <x-pixel.input name="username" :label="__('Username')" placeholder="pixel_punk" :autofocus="true" />

                <x-pixel.input name="password" type="password" :label="__('Password')" placeholder="••••••••"
                    autocomplete="current-password" />
            </div>

            <x-pixel.checkbox name="remember" :checked="true">
                {{ __('Remember me') }}
            </x-pixel.checkbox>

            <x-pixel.captcha />

            <x-pixel.button size="lg" :full-width="true">
                {{ __('▶ Play now') }}
            </x-pixel.button>
        </form>

        <p class="pixel-meta">
            @if (setting('disable_registration') !== '1')
                {{ __('New in town?') }}
                <a class="pixel-link" href="{{ route('register') }}">{{ __('Create account') }}</a>
                <br>
            @endif

            <a class="pixel-link" href="{{ route('forgot.password.get') }}">
                {{ __('Forgot your password?') }}
            </a>
        </p>
    </div>
</div>
