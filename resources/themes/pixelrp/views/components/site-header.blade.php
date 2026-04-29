@props(['cta' => 'play'])

<header class="w-full bg-(--color-ink) border-b-4 border-(--color-coin)">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-8 py-3">
        <a href="{{ route('welcome') }}" class="flex items-center gap-2.5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-[4px] bg-(--color-coin) text-(--color-ink) text-[14px] font-black tracking-[1px] border-[3px] border-(--color-ink)" style="font-family: Inter, sans-serif; box-shadow: 4px 4px 0 0 var(--color-ink);">PT</span>
            <span class="pt-wordmark text-[22px] text-(--color-coin)">PIXEL TOWER</span>
        </a>

        @if ($cta === 'enter')
            <a href="{{ route('nitro-client') }}" data-turbolinks="false" class="pt-btn pt-btn--secondary pt-btn--sm">
                {{ __('Enter Town') }} →
            </a>
        @else
            <a href="@auth{{ route('nitro-client') }}@else{{ route('welcome') }}@endauth" data-turbolinks="false" class="pt-btn pt-btn--secondary pt-btn--sm">
                {{ __('Play now') }} →
            </a>
        @endif
    </div>
</header>
