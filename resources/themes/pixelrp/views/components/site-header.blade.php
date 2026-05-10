@props(['cta' => 'play'])

<header class="w-full bg-(--color-ink-soft) border-b-4 border-(--color-coin)">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-8 py-3">
        <a href="{{ route('welcome') }}" class="flex items-center">
            <img src="{{ asset('assets/images/pixelrp/logo.gif') }}" alt="PixelRP"
                style="image-rendering: pixelated; height: 40px; width: auto;">
        </a>

        @auth
            <a href="{{ route('nitro-client') }}" data-turbolinks="false"
               onclick="event.preventDefault(); const w = window.open(this.href, 'pixelrp-game', 'width=1280,height=800,resizable=yes,scrollbars=yes'); if (w) { w.focus(); } else { window.dispatchEvent(new CustomEvent('popup-blocked')); }"
               class="pt-btn pt-btn--secondary pt-btn--sm">
                {{ __('Play now') }}
            </a>
        @else
            <a href="{{ route('welcome') }}" class="pt-btn pt-btn--secondary pt-btn--sm">
                {{ __('Log in') }}
            </a>
        @endauth
    </div>
</header>
