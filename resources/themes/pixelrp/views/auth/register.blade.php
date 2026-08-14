{{--
    Reached two ways: Fortify's registerView (plain /register) and
    UserReferralController (/register/{referral_code}). Only $referral_code is
    guaranteed to be present, so nothing here may depend on the article and
    photo data Fortify happens to pass alongside it.
--}}
<x-guest-layout>
    @push('title', __('Create account'))

    <img class="pixel-logo pixel-logo--sm" src="{{ asset('assets/images/pixelrp/logo-animated.gif') }}"
        alt="{{ setting('hotel_name') }}" width="201" height="99">

    <div class="pixel-card pixel-card--wide">
        {{-- Boarding pass header: the departure board flips through origin
             cities while the destination stays put. --}}
        <div class="pixel-pass">
            <div class="pixel-pass__row">
                <span class="pixel-pass__meta">{{ __('Now departing') }}</span>
                <span class="pixel-pass__meta pixel-pass__boarding">● {{ __('Boarding') }}</span>
            </div>

            <div class="pixel-pass__route">
                <span class="pixel-pass__code" data-departure-board>HAB</span>
                <span class="pixel-pass__arrow" aria-hidden="true">▶▶▶</span>
                <span class="pixel-pass__code">PXL <small>(SFO)</small></span>
            </div>

            <div class="pixel-pass__stub">
                <span>{{ __('Flight PXL-26') }}</span>
                <span>{{ __('Gate 1UP') }}</span>
                <span>{{ __('Seat ∞') }}</span>
            </div>
        </div>

        <div class="pixel-card__body pixel-card__body--stub">
            <p class="pixel-card__lede">{{ __('Fill out your boarding pass to join the city.') }}</p>

            <x-pixel.alert :handled="['username', 'mail', 'password', 'beta_code']" />

            <form class="flex flex-col gap-4" method="POST" action="{{ route('register') }}">
                @csrf

                <div class="flex flex-col gap-3">
                    <x-pixel.input name="username" :label="__('In-game username')" placeholder="pixel_punk"
                        :autofocus="true" autocomplete="username" />

                    <x-pixel.input name="mail" type="email" :label="__('Email address')" placeholder="you@example.com"
                        autocomplete="email" />

                    <x-pixel.input name="password" type="password" :label="__('Password')" placeholder="••••••••"
                        autocomplete="new-password"
                        :hint="__('At least 12 characters, with upper and lower case, a number and a symbol.')" />

                    <x-pixel.input name="password_confirmation" type="password" :label="__('Verify password')"
                        placeholder="••••••••" autocomplete="new-password" />

                    @if (setting('requires_beta_code'))
                        <x-pixel.input name="beta_code" :label="__('Beta code')"
                            :placeholder="__('Enter your beta code')"
                            :hint="__('Registration is invite only right now.')" />
                    @endif
                </div>

                <x-pixel.checkbox name="terms">
                    {{ __('I accept the') }}
                    <a class="pixel-link" href="{{ route('help-center.rules.index') }}" target="_blank"
                        rel="noopener">{{ __('terms & rules') }}</a>
                </x-pixel.checkbox>

                {{-- Credits the referrer once the account is created. --}}
                <input type="hidden" name="referral_code" value="{{ $referral_code }}">

                <x-pixel.captcha />

                <x-pixel.button size="lg" :full-width="true">
                    {{ __('▶ Board now') }}
                </x-pixel.button>
            </form>

            <p class="pixel-meta">
                {{ __('Already a citizen?') }}
                <a class="pixel-link" href="{{ route('welcome') }}">{{ __('Log in') }}</a>
            </p>
        </div>
    </div>

    @push('scripts')
        <script>
            // Departure board: scramble the origin code, then settle it
            // left-to-right. Purely decorative, so it stays put for anyone who
            // has asked for reduced motion.
            (() => {
                const board = document.querySelector('[data-departure-board]');

                if (! board || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    return;
                }

                const cities = ['HAB', 'WAV', 'VGE'];
                const glyphs = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                const randomGlyph = () => glyphs[Math.floor(Math.random() * glyphs.length)];
                let index = 0;

                setInterval(() => {
                    const next = cities[(index + 1) % cities.length];
                    let ticks = 0;

                    const spin = setInterval(() => {
                        ticks += 1;

                        if (ticks >= 10) {
                            clearInterval(spin);
                            index = (index + 1) % cities.length;
                            board.textContent = cities[index];

                            return;
                        }

                        const settled = ticks >= 8 ? 2 : ticks >= 6 ? 1 : 0;
                        board.textContent = next.slice(0, settled)
                            + Array.from({ length: 3 - settled }, randomGlyph).join('');
                    }, 70);
                }, 7000);
            })();
        </script>
    @endpush
</x-guest-layout>
