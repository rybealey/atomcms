{{-- Logged-in home for PixelRP. Profile/Corporation/Bank/Stats values are real;
     Gang stats stay placeholder until those tables exist. --}}
<x-app-layout>
    @push('title', __('Home'))

    @php
        $health = ['current' => $stats['hp'] ?? 0, 'max' => max($stats['max_hp'] ?? 1, 1)];
        $energy = ['current' => $stats['energy'] ?? 0, 'max' => max($stats['max_energy'] ?? 1, 1)];
        $gang = ['name' => null, 'rank' => null, 'heists' => 0, 'turfs' => 0];
        $avatarUrl = $user?->look ? setting('avatar_imager') . $user->look . '&direction=2&head_direction=3&gesture=sml&action=wav&size=l' : null;
    @endphp

    <div class="flex flex-col gap-6">
        <div
            x-data="{
                blocked: false,
                showHelp: false,
                init() {
                    try {
                        if (sessionStorage.getItem('popupsAllowed') === '1') return;
                    } catch (e) {}
                    this.detect();
                },
                detect() {
                    let win = null;
                    try {
                        win = window.open('about:blank', '_blank',
                            'width=1,height=1,left=-10000,top=-10000');
                    } catch (e) {}
                    if (!win || win.closed || typeof win.closed === 'undefined') {
                        this.blocked = true;
                        return;
                    }
                    try { win.close(); } catch (e) {}
                    try { sessionStorage.setItem('popupsAllowed', '1'); } catch (e) {}
                    this.blocked = false;
                },
                tryEnable() {
                    this.detect();
                    if (this.blocked) this.showHelp = true;
                },
                dismiss() {
                    this.blocked = false;
                    try { sessionStorage.setItem('popupsAllowed', '1'); } catch (e) {}
                }
            }"
            x-show="blocked"
            style="display: none"
            class="flex flex-col gap-2 rounded-md border-2 border-(--color-coin) bg-(--color-coin)/10 px-4 py-3"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="text-[13px] font-bold text-white">
                    {{ __('Your browser is blocking popups. Some features like linking your Discord account and purchasing Diamonds need popups to work.') }}
                </span>
                <div class="flex shrink-0 gap-2">
                    <button type="button" @click="tryEnable()" class="pt-btn pt-btn--primary pt-btn--sm">
                        {{ __('Enable popups') }}
                    </button>
                    <button type="button" @click="dismiss()" class="pt-btn pt-btn--secondary pt-btn--sm">
                        {{ __('Dismiss') }}
                    </button>
                </div>
            </div>
            <p x-show="showHelp" x-cloak class="text-[12px] font-medium text-(--color-hero-sub)">
                {{ __('Still blocked. Click the popup-blocked icon in your address bar, choose "Always allow popups from this site," then reload.') }}
            </p>
        </div>

        <header class="flex flex-col gap-1">
            <span class="text-[12px] font-bold tracking-[3px] uppercase text-(--color-coin)">{{ __('Welcome back') }}</span>
            <h1 class="text-[36px] font-black leading-none text-white">{{ $user->username }}</h1>
        </header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[2fr_1fr]">
            {{-- Profile card --}}
            <section class="pt-stat-card pt-stat-card--profile">
                <div class="pt-card-header pt-card-header--profile">
                    <h2>{{ __('PROFILE') }}</h2>
                </div>
                <div class="pt-stat-card-body">
                    <div class="flex flex-col gap-5 md:flex-row md:gap-5">
                        {{-- Avatar tile (140x280): dark fill with subtle diagonal stripes, yellow stroke --}}
                        <div class="pt-avatar-tile relative shrink-0 self-center md:self-start overflow-hidden rounded-md border-2 border-(--color-coin)">
                            @if ($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="{{ $user->username }}"
                                    style="image-rendering: pixelated;"
                                    onerror="this.replaceWith(Object.assign(document.createElement('div'), {className: 'flex h-full w-full items-center justify-center', innerHTML: '<svg width=\'64\' height=\'64\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'#1E5BAA\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><circle cx=\'12\' cy=\'8\' r=\'4\'/><path d=\'M4 21v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2\'/></svg>'}))"
                                    class="absolute left-1/2 top-[44%] -translate-x-1/2 -translate-y-1/2 max-h-[260px]">
                            @else
                                <div class="flex h-full w-full items-center justify-center">
                                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#1E5BAA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2"/></svg>
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col gap-3.5 min-w-0">
                            {{-- Name box --}}
                            <div class="rounded-md border-2 border-(--color-panel-stroke) bg-(--color-ink-panel) px-5 py-3.5 text-center">
                                <span class="text-[22px] font-black text-white leading-none">{{ $user->username }}</span>
                            </div>

                            {{-- Health + Energy paired panels --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div class="pt-panel">
                                    <div class="pt-bar-label">
                                        <span class="pt-bar-label-key">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="#F04848" stroke="#F04848" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                            {{ __('Health') }}
                                        </span>
                                        <span class="pt-bar-label-value">{{ $health['current'] }}/{{ $health['max'] }}</span>
                                    </div>
                                    <div class="pt-bar-track">
                                        <div class="pt-bar-fill pt-bar-fill--health" style="width: {{ ($health['current'] / max($health['max'], 1)) * 100 }}%"></div>
                                    </div>
                                </div>
                                <div class="pt-panel">
                                    <div class="pt-bar-label">
                                        <span class="pt-bar-label-key">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="#4E85E0" stroke="#4E85E0" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                            {{ __('Energy') }}
                                        </span>
                                        <span class="pt-bar-label-value">{{ $energy['current'] }}/{{ $energy['max'] }}</span>
                                    </div>
                                    <div class="pt-bar-track">
                                        <div class="pt-bar-fill pt-bar-fill--energy" style="width: {{ ($energy['current'] / max($energy['max'], 1)) * 100 }}%"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Currency tiles (vertical: icon+label on top, big value below) --}}
                            <div class="grid grid-cols-3 gap-2.5">
                                <div class="pt-stat-pill">
                                    <div class="pt-stat-pill-head">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#5BC34A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
                                        {{ __('Cash') }}
                                    </div>
                                    <div class="pt-stat-pill-value">${{ number_format($cash) }}</div>
                                </div>
                                <div class="pt-stat-pill">
                                    <div class="pt-stat-pill-head">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#FFC700" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="22" x2="21" y2="22"/><line x1="6" y1="18" x2="6" y2="11"/><line x1="10" y1="18" x2="10" y2="11"/><line x1="14" y1="18" x2="14" y2="11"/><line x1="18" y1="18" x2="18" y2="11"/><polygon points="12 2 20 7 4 7 12 2"/></svg>
                                        {{ __('Bank') }}
                                    </div>
                                    <div class="pt-stat-pill-value">${{ number_format($bank) }}</div>
                                </div>
                                <div class="pt-stat-pill">
                                    <div class="pt-stat-pill-head">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#5BC0DE" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 13L2 9z"/><path d="M11 3 8 9l4 13 4-13-3-6"/><path d="M2 9h20"/></svg>
                                        {{ __('Diamonds') }}
                                    </div>
                                    <div class="pt-stat-pill-value">{{ number_format($diamonds) }}</div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </section>

            {{-- Right column: Corporation + Gang --}}
            <div class="flex flex-col gap-6">
                {{-- Corporation card --}}
                <section class="pt-stat-card">
                    <div class="pt-card-header pt-card-header--stat">
                        <h2>{{ __('CORPORATION') }}</h2>
                    </div>
                    <div class="pt-stat-card-body">
                        @if ($employment)
                            <div class="flex items-center gap-3.5">
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-md border-2 border-(--color-coin) bg-(--color-ink-panel)">
                                    @if (!empty($employment['badge_code']))
                                        <img src="{{ setting('badges_path') }}/{{ $employment['badge_code'] }}.gif" alt="" style="image-rendering: pixelated;" class="max-h-10 max-w-10">
                                    @else
                                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#FFC700" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                                    @endif
                                </div>
                                <div class="flex flex-col gap-0.5 min-w-0">
                                    <span class="text-[16px] font-black text-white leading-tight">{{ $employment['corp_name'] }}</span>
                                    <span class="text-[13px] font-medium text-(--color-hero-sub)">{{ $employment['rank_title'] }}</span>
                                </div>
                                @if ($stats['is_on_duty'])
                                    <span class="ml-auto inline-flex items-center gap-1.5 rounded-md border-2 border-(--color-ink) bg-(--color-xp-green) px-2 py-1 text-[10px] font-black uppercase tracking-widest text-(--color-ink)" style="box-shadow: 2px 2px 0 0 var(--color-ink);">
                                        <span class="h-2 w-2 rounded-full bg-(--color-ink)"></span>
                                        {{ __('On duty') }}
                                    </span>
                                @endif
                            </div>
                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="pt-panel pt-panel--tight items-center text-center">
                                    <span class="pt-panel-label">{{ __('Weekly shifts') }}</span>
                                    <span class="pt-panel-value">{{ $employment['weekly_shifts'] }}</span>
                                </div>
                                <div class="pt-panel pt-panel--tight items-center text-center">
                                    <span class="pt-panel-label">{{ __('Total shifts') }}</span>
                                    <span class="pt-panel-value">{{ $employment['total_shifts'] }}</span>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col items-center gap-3 py-2 text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-md border-2 border-(--color-panel-stroke) bg-(--color-ink-panel) text-(--color-hero-sub)">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><line x1="2" y1="13" x2="22" y2="13"/></svg>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-[15px] font-black text-white">{{ __('Unemployed') }}</span>
                                    <span class="text-[12px] font-medium text-(--color-hero-sub)">{{ __('Visit a corporation HQ to apply.') }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Discord card: link account to show Pixeltower on Discord profile --}}
                @if (($discord['configured'] ?? false))
                <section class="pt-stat-card">
                    <div class="pt-card-header pt-card-header--stat">
                        <h2>{{ __('DISCORD') }}</h2>
                    </div>
                    <div class="pt-stat-card-body">
                        @if ($discord['state'] === 'connected')
                            <div class="flex items-center gap-3.5">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border-2 border-(--color-coin) bg-(--color-ink-panel)">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="#5865F2"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.07.07 0 0 0-.073.035 13.71 13.71 0 0 0-.609 1.249 18.27 18.27 0 0 0-5.487 0 13.21 13.21 0 0 0-.617-1.249.073.073 0 0 0-.073-.035 19.736 19.736 0 0 0-4.885 1.515.066.066 0 0 0-.03.027C.533 9.046-.32 13.58.099 18.057a.083.083 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.009c.12.099.245.198.372.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.891.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.84 19.84 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.331c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.974 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                                </div>
                                <div class="flex flex-col gap-0.5 min-w-0 flex-1">
                                    <span class="text-[15px] font-black text-white leading-tight">@{{ $discord['username'] }}</span>
                                    <span class="text-[12px] font-medium text-(--color-xp-green)">{{ __('Showing on your Discord profile') }}</span>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('discord.oauth.disconnect') }}" class="mt-2">
                                @csrf
                                <button type="submit" class="pt-btn pt-btn--secondary pt-btn--sm w-full">
                                    {{ __('Disconnect') }}
                                </button>
                            </form>
                        @elseif ($discord['state'] === 'linked')
                            <div class="flex items-center gap-3.5">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border-2 border-(--color-panel-stroke) bg-(--color-ink-panel)">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="#5865F2"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.07.07 0 0 0-.073.035 13.71 13.71 0 0 0-.609 1.249 18.27 18.27 0 0 0-5.487 0 13.21 13.21 0 0 0-.617-1.249.073.073 0 0 0-.073-.035 19.736 19.736 0 0 0-4.885 1.515.066.066 0 0 0-.03.027C.533 9.046-.32 13.58.099 18.057a.083.083 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.009c.12.099.245.198.372.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.891.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.84 19.84 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.331c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.974 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                                </div>
                                <div class="flex flex-col gap-0.5 min-w-0 flex-1">
                                    <span class="text-[15px] font-black text-white leading-tight">@{{ $discord['username'] }}</span>
                                    <span class="text-[12px] font-medium text-(--color-hero-sub)">{{ __('Linked. Show Pixeltower on your profile?') }}</span>
                                </div>
                            </div>
                            <a href="{{ route('discord.oauth.start') }}" class="pt-btn pt-btn--primary pt-btn--sm w-full mt-2">
                                {{ __('Show on profile') }}
                            </a>
                        @else
                            <div class="flex flex-col items-center gap-3 py-2 text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-md border-2 border-(--color-panel-stroke) bg-(--color-ink-panel)">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="#5865F2"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.07.07 0 0 0-.073.035 13.71 13.71 0 0 0-.609 1.249 18.27 18.27 0 0 0-5.487 0 13.21 13.21 0 0 0-.617-1.249.073.073 0 0 0-.073-.035 19.736 19.736 0 0 0-4.885 1.515.066.066 0 0 0-.03.027C.533 9.046-.32 13.58.099 18.057a.083.083 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.009c.12.099.245.198.372.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.891.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.84 19.84 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.331c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.974 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-[15px] font-black text-white">{{ __('Not connected') }}</span>
                                    <span class="text-[12px] font-medium text-(--color-hero-sub)">{{ __('Show Habbo Roleplay and your motto on Discord.') }}</span>
                                </div>
                                <a href="{{ route('discord.oauth.start') }}" class="pt-btn pt-btn--primary pt-btn--sm w-full mt-1">
                                    {{ __('Connect Discord') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </section>
                @endif

                {{-- Gang card --}}
                <section class="pt-stat-card">
                    <div class="pt-card-header pt-card-header--stat">
                        <h2>{{ __('GANG') }}</h2>
                    </div>
                    <div class="pt-stat-card-body">
                        @if ($gang['name'])
                            <div class="flex items-center gap-3.5">
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-md border-2 border-(--color-panel-stroke) bg-(--color-ink-panel)">
                                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#D8E6FA" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <div class="flex flex-col gap-0.5 min-w-0">
                                    <span class="text-[16px] font-black text-white leading-tight">{{ $gang['name'] }}</span>
                                    <span class="text-[13px] font-medium text-(--color-hero-sub)">{{ $gang['rank'] }}</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="pt-panel pt-panel--tight items-center text-center">
                                    <span class="pt-panel-label">{{ __('Heists') }}</span>
                                    <span class="pt-panel-value">{{ $gang['heists'] }}</span>
                                </div>
                                <div class="pt-panel pt-panel--tight items-center text-center">
                                    <span class="pt-panel-label">{{ __('Turfs') }}</span>
                                    <span class="pt-panel-value">{{ $gang['turfs'] }}</span>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col items-center gap-3 py-2 text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-md border-2 border-(--color-panel-stroke) bg-(--color-ink-panel) text-(--color-hero-sub)">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-[15px] font-black text-white">{{ __('No gang') }}</span>
                                    <span class="text-[12px] font-medium text-(--color-hero-sub)">{{ __('Find a crew on the streets.') }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
