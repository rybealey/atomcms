{{-- Pixel Tower CMS footer. The "all servers online" indicator is hardcoded green for now;
     wire to a real emulator health check when the status endpoint exists. --}}
<footer class="mt-auto w-full bg-(--color-ink) border-t-4 border-(--color-coin) text-white/85">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 py-4 text-[11px] font-bold tracking-widest uppercase md:flex-row">
        <div class="opacity-80">
            © {{ date('Y') }} RIZZ ENTERPRISES, LLC
        </div>

        <nav class="flex items-center gap-5">
            <a class="hover:text-(--color-coin)" href="{{ route('help-center.rules.index') }}">{{ __('Terms') }}</a>
            <a class="hover:text-(--color-coin)" href="#">{{ __('Privacy') }}</a>
            <a class="hover:text-(--color-coin)" href="#">{{ __('Safety') }}</a>
            <a class="hover:text-(--color-coin)" href="#">{{ __('Contact') }}</a>
        </nav>

        <div class="flex items-center gap-2">
            <span class="pt-server-dot"></span>
            <span>{{ __('All servers online') }}</span>
        </div>
    </div>
</footer>
