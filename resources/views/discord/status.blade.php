<x-app-layout>
    @push('title', __('Discord'))

    <div class="col-span-12 flex justify-center">
        <div class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-4 flex items-center gap-3">
                <svg class="h-8 w-8 shrink-0 text-[#5865F2]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" fill="currentColor" aria-hidden="true">
                    <path d="M524.5 69.8a1.5 1.5 0 0 0-.8-.7A485.1 485.1 0 0 0 404.1 32a1.8 1.8 0 0 0-1.9.9 337.5 337.5 0 0 0-14.9 30.6 447.8 447.8 0 0 0-134.4 0 309.5 309.5 0 0 0-15.1-30.6 1.9 1.9 0 0 0-1.9-.9A483.7 483.7 0 0 0 116.1 69.1a1.7 1.7 0 0 0-.8.7C39.1 183.7 18.2 294.7 28.4 404.4a2 2 0 0 0 .8 1.4A487.7 487.7 0 0 0 176 479.9a1.9 1.9 0 0 0 2.1-.7A348.2 348.2 0 0 0 208.1 430.4a1.9 1.9 0 0 0-1-2.6 321.2 321.2 0 0 1-45.9-21.9 1.9 1.9 0 0 1-.2-3.1c3.1-2.3 6.2-4.7 9.1-7.1a1.8 1.8 0 0 1 1.9-.3c96.2 43.9 200.4 43.9 295.5 0a1.8 1.8 0 0 1 1.9.2c2.9 2.4 6 4.9 9.1 7.2a1.9 1.9 0 0 1-.2 3.1 301.4 301.4 0 0 1-45.9 21.8 1.9 1.9 0 0 0-1 2.6 391.1 391.1 0 0 0 30 48.8 1.9 1.9 0 0 0 2.1.7A486 486 0 0 0 610.7 405.7a1.9 1.9 0 0 0 .8-1.4c12.2-126.7-20.6-236.8-87-334.5zM222.5 337.6c-29 0-52.8-26.6-52.8-59.2s23.4-59.3 52.8-59.3c29.7 0 53.3 26.8 52.8 59.2 0 32.7-23.4 59.3-52.8 59.3zm195.4 0c-29 0-52.8-26.6-52.8-59.2s23.3-59.3 52.8-59.3c29.7 0 53.3 26.8 52.8 59.2 0 32.7-23.2 59.3-52.8 59.3z"/>
                </svg>
                <h1 class="text-xl font-semibold tracking-tight">{{ __('Discord Connection') }}</h1>
            </div>

            @if ($linked)
                <div class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
                    <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                    </svg>
                    <span>
                        {{ __('Your Discord account is connected.') }}
                        @if ($linkedAt > 0)
                            {{ __('Linked :time.', ['time' => \Carbon\Carbon::createFromTimestamp($linkedAt)->diffForHumans()]) }}
                        @endif
                    </span>
                </div>

                <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Your nickname in the PixelRP Discord server matches your in-game name, and you hold the Verified role. Your Discord details are never shown in-game.') }}
                </p>

                <div class="flex items-center gap-3">
                    <a href="{{ $inviteUrl }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 rounded-lg bg-[#5865F2] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4752c4]">
                        {{ __('Open the Discord server') }}
                    </a>

                    <form method="POST" action="{{ route('discord.unlink') }}"
                          onsubmit="return confirm(@js(__('Disconnect your Discord account? Your Verified role and synced nickname will be removed.')));">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                            {{ __('Disconnect') }}
                        </button>
                    </form>
                </div>
            @else
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Connect your Discord account to join the PixelRP server automatically, get the Verified role, and have your server nickname match your in-game name. Your Discord details are never shown in-game.') }}
                </p>

                @if ($configured)
                    <a href="{{ route('discord.connect') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-[#5865F2] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4752c4]">
                        {{ __('Connect Discord') }}
                    </a>
                @else
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                        {{ __('Discord linking is not available right now. Please try again later.') }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
