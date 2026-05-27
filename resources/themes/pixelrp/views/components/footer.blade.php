@php
    // Mirrors the in-game deploy overlay: read the same deploy-state.json the
    // Nitro client polls via /api/deploy-status. SSR sets the initial dot
    // colour and label so a no-JS or pre-Alpine paint already matches reality.
    // Shutdown (operator-triggered :shutdown lockdown) supersedes "updating"
    // because it gates Play Now entirely, not just announces an incoming
    // deploy.
    $deployStatus = (function () {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (! $disk->exists('deploy-state.json')) return 'idle';
        $decoded = json_decode((string) $disk->get('deploy-state.json'), true);
        return is_array($decoded) ? ($decoded['status'] ?? 'idle') : 'idle';
    })();
    $isUpdating = in_array($deployStatus, ['announcing', 'deploying'], true);
    $isShutdown = app(\App\Services\ServerStatusService::class)->isShutdown();
    $initialLabel = $isShutdown ? __('Server maintenance') : ($isUpdating ? __('All servers updating') : __('All servers online'));
@endphp
<footer class="mt-auto w-full bg-(--color-ink-soft) border-t-4 border-(--color-coin)">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-8 py-3.5 text-[11px] font-bold tracking-[1.5px] uppercase">
        <div class="text-(--color-footer-text)">
            {{ __('PixelRP is a not for profit educational project.') }}
        </div>

        <div class="flex items-center gap-2"
             x-data="{
                 updating: {{ $isUpdating ? 'true' : 'false' }},
                 shutdown: {{ $isShutdown ? 'true' : 'false' }},
                 async poll() {
                     try {
                         const r = await fetch('/api/deploy-status', { cache: 'no-store' });
                         if (!r.ok) return;
                         const j = await r.json();
                         const s = j?.data?.status;
                         this.updating = (s === 'announcing' || s === 'deploying');
                         this.shutdown = !!j?.data?.shutdown;
                     } catch (e) {}
                 },
                 init() { this.poll(); setInterval(() => this.poll(), 10000); }
             }">
            <span class="pt-server-dot" :class="{ 'pt-server-dot--maintenance': shutdown, 'pt-server-dot--updating': !shutdown && updating }"></span>
            <span class="text-white"
                  x-text="shutdown ? {{ \Illuminate\Support\Js::from(__('Server maintenance')) }} : (updating ? {{ \Illuminate\Support\Js::from(__('All servers updating')) }} : {{ \Illuminate\Support\Js::from(__('All servers online')) }})">{{ $initialLabel }}</span>
        </div>
    </div>
</footer>
