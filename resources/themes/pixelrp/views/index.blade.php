{{-- PixelRP welcome (login) screen — the guest landing IS the login page. --}}
<x-guest-layout>
    @push('title', __('Sign in'))

    <x-slot:hero>
        <x-feature-checklist
            heading="{{ __('Sign in to your account') }}"
            sub="{{ __('Manage your character, gang, jobs and farm from one dashboard. Closed beta access required.') }}"
        />
    </x-slot:hero>

    <x-auth-card title="{{ __('LOG IN') }}" sub="{{ __('Welcome back, citizen.') }}">
        @if ($errors->any())
            <div class="mb-4 rounded border-2 border-(--color-danger) bg-(--color-danger)/10 p-3 text-[12px] font-bold text-(--color-danger)">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
            @csrf

            <div class="flex flex-col gap-2">
                <label for="username" class="pt-section-label text-(--color-ink)">{{ __('Username') }}</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}"
                    placeholder="PixelKnight92" autofocus autocomplete="username"
                    class="pt-input @error('username') border-(--color-danger) @enderror">
                @error('username')<p class="pt-input-error">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2" x-data="{ show: false }">
                <label for="password" class="pt-section-label text-(--color-ink)">{{ __('Password') }}</label>
                <div class="relative">
                    <input id="password" name="password" :type="show ? 'text' : 'password'"
                        placeholder="••••••••••" autocomplete="current-password"
                        class="pt-input pr-12 @error('password') border-(--color-danger) @enderror">
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-(--color-ink)/70 hover:text-(--color-ink)" tabindex="-1">
                        <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
                @error('password')<p class="pt-input-error">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 text-[13px] font-bold text-(--color-ink) cursor-pointer">
                    <input type="checkbox" name="remember" class="pt-checkbox">
                    {{ __('Remember me') }}
                </label>
                <a href="{{ route('forgot.password.get') }}" class="pt-link">{{ __('Forgot password?') }}</a>
            </div>

            @if (setting('google_recaptcha_enabled'))
                <div class="g-recaptcha" data-sitekey="{{ config('habbo.site.recaptcha_site_key') }}"></div>
            @endif

            @if (setting('cloudflare_turnstile_enabled'))
                <x-turnstile />
            @endif

            <button type="submit" class="pt-btn pt-btn--primary pt-btn--primary-lg pt-btn--block mt-2">
                {{ __('LOG IN') }}
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>

        <x-slot:footer>
            {{ __('New to PixelRP?') }}
            <a href="{{ route('register') }}" class="pt-link ml-1 uppercase tracking-[1px]">{{ __('Get started') }}</a>
        </x-slot:footer>
    </x-auth-card>
</x-guest-layout>
