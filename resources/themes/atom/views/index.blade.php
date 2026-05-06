<x-app-layout>
    @push('title', __('Welcome to the best hotel on the web!'))

    <div class="col-span-12 space-y-14">
        @auth
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
                class="col-span-12 w-full flex flex-col gap-2 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 rounded-lg px-4 py-3"
            >
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <span class="text-sm text-amber-800 dark:text-amber-100">
                        {{ __('Your browser is blocking popups. Some features like linking your Discord account and purchasing Diamonds need popups to work.') }}
                    </span>
                    <div class="flex gap-2 shrink-0">
                        <button type="button" @click="tryEnable()"
                            class="border-2 border-yellow-400 bg-[#eeb425] hover:bg-[#d49f1c] text-white font-semibold text-sm px-4 py-1 rounded transition">
                            {{ __('Enable popups') }}
                        </button>
                        <button type="button" @click="dismiss()"
                            class="border border-gray-300 dark:border-gray-500 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold px-4 py-1 rounded transition">
                            {{ __('Dismiss') }}
                        </button>
                    </div>
                </div>
                <p x-show="showHelp" x-cloak class="text-xs text-amber-700 dark:text-amber-200">
                    {{ __('Still blocked. Click the popup-blocked icon in your address bar, choose "Always allow popups from this site," then reload.') }}
                </p>
            </div>
        @endauth

        <div class="col-span-12">
            <x-content.guest-content-card icon="hotel-icon">
                <x-slot:title>
                    {{ __('Latest news') }}
                </x-slot:title>

                <x-slot:under-title>
                    {{ __('Keep up to date with the latest hotel gossip.') }}
                </x-slot:under-title>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    @forelse($articles as $article)
                        <x-article-card :article="$article" />
                    @empty
                        <x-filler-article-card />
                    @endforelse
                </div>
            </x-content.guest-content-card>
        </div>

        @if($photos)
            <div class="col-span-12">
                <x-content.guest-content-card icon="camera-icon">
                    <x-slot:title>
                        {{ __('Latest Photos') }}
                    </x-slot:title>

                    <x-slot:under-title>
                        {{ __('Have a look at some of the great moments captured by users around the hotel.') }}
                    </x-slot:under-title>
                    <x-photos :photos="$photos" />
                </x-content.guest-content-card>
            </div>
        @endif
    </div>

    @push('javascript')
        <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.umd.js"></script>
    @endpush

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css" />
</x-app-layout>
