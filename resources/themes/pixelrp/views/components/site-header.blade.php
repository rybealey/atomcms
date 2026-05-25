@props(['cta' => 'play'])

<header class="w-full bg-(--color-ink-soft) border-b-4 border-(--color-coin)">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-8 py-3">
        <a href="{{ route('welcome') }}" class="flex items-center">
            <img src="{{ asset('assets/images/pixelrp/logo.gif') }}" alt="PixelRP"
                style="image-rendering: pixelated; height: 56px; width: auto;">
        </a>

        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2 text-sm">
                <span class="inline-block h-2 w-2 rounded-full bg-(--color-xp-green)" style="box-shadow: 0 0 6px var(--color-xp-green);"></span>
                <span class="font-black text-white tabular-nums">{{ DB::table('users')->where('online', '1')->count() }}</span>
                <span class="font-medium text-(--color-hero-sub)">{{ __('online') }}</span>
            </div>

            @auth
                <a href="{{ route('nitro-client') }}" data-turbolinks="false"
                   onclick="event.preventDefault(); const w = window.open(this.href, 'pixelrp-game'); if (w) { w.focus(); } else { window.dispatchEvent(new CustomEvent('popup-blocked')); }"
                   class="pt-btn pt-btn--secondary pt-btn--sm">
                    {{ __('Play now') }}
                </a>
            @else
                <a href="{{ route('welcome') }}" class="pt-btn pt-btn--secondary pt-btn--sm">
                    {{ __('Log in') }}
                </a>
            @endauth
        </div>
    </div>
</header>
