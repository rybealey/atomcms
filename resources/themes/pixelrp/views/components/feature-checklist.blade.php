@props(['eyebrow' => 'PIXELRP', 'heading', 'sub' => null])

<section class="flex w-full max-w-xl flex-col gap-6 text-white">
    <div class="flex flex-col gap-4">
        <span class="pt-eyebrow">{{ $eyebrow }}</span>
        <h1 class="text-[44px] font-black leading-[1.05] tracking-tight">{{ $heading }}</h1>
        @if ($sub)
            <p class="max-w-md text-[16px] font-medium leading-[1.5] text-(--color-hero-sub)">{{ $sub }}</p>
        @endif
    </div>

    <ul class="flex flex-col gap-3">
        @foreach (['Manage your character and inventory', 'Track gang reputation and territory', 'Grind shifts at your favorite local business', 'Tend crops and read the town ledger'] as $item)
            <li class="flex items-center gap-2.5 text-[14px] font-bold text-white">
                <span class="pt-checktile">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                {{ __($item) }}
            </li>
        @endforeach
    </ul>
</section>
