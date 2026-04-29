{{-- PixelRP CMS footer. The "all servers online" indicator is hardcoded green;
     wire to a real emulator health check when the status endpoint exists. --}}
<footer class="mt-auto w-full bg-(--color-ink-soft) border-t-4 border-(--color-coin)">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-8 py-3.5 text-[11px] font-bold tracking-[1.5px] uppercase">
        <div class="text-(--color-footer-text)">
            © {{ date('Y') }} RIZZ ENTERPRISES, LLC
        </div>

        <div class="flex items-center gap-2">
            <span class="pt-server-dot"></span>
            <span class="text-white">{{ __('All servers online') }}</span>
        </div>
    </div>
</footer>
