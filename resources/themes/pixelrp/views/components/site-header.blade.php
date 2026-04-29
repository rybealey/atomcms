@props(['cta' => 'play'])

<header class="w-full bg-(--color-ink-soft) border-b-4 border-(--color-coin)">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-8 py-3">
        <a href="{{ route('welcome') }}" class="flex items-center">
            <img src="{{ asset('assets/images/pixelrp/logo.gif') }}" alt="PixelRP"
                style="image-rendering: pixelated; height: 40px; width: auto;">
        </a>

        @auth
            @if ($cta === 'enter')
                <a href="{{ route('nitro-client') }}" data-turbolinks="false" target="pixelrp-game"
                   onclick="const w=window.open(this.href,this.target,'popup=yes,width=1280,height=800,resizable=yes'); if(w){event.preventDefault();w.focus();}"
                   class="pt-btn pt-btn--secondary pt-btn--sm">
                    {{ __('Play now') }}
                </a>
            @else
                <a href="{{ route('nitro-client') }}" data-turbolinks="false" target="pixelrp-game"
                   onclick="const w=window.open(this.href,this.target,'popup=yes,width=1280,height=800,resizable=yes'); if(w){event.preventDefault();w.focus();}"
                   class="pt-btn pt-btn--secondary pt-btn--sm">
                    {{ __('Play now') }}
                </a>
            @endif
        @else
            <a href="{{ route('welcome') }}" class="pt-btn pt-btn--secondary pt-btn--sm">
                {{ __('Log in') }}
            </a>
        @endauth
    </div>
</header>
