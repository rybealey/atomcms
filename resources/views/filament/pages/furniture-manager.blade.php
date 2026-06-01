<x-filament-panels::page>
    @php($data = $this->furniPage())

    <x-filament::section>
        <x-slot name="heading">Imported furniture</x-slot>
        <x-slot name="description">{{ $data['total'] }} furni. Editing or deleting commits to main and triggers a production deploy.</x-slot>

        <div class="mb-4">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Search by name, classname, or sub-page"
                />
            </x-filament::input.wrapper>
        </div>

        @if (empty($data['items']))
            <p class="text-sm text-gray-500">No furni match. Imported furni show up here once the worker has published its catalog index.</p>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($data['items'] as $f)
                    <div class="flex flex-col gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded bg-gray-50 dark:bg-gray-800" style="image-rendering: pixelated">
                                @if (! empty($f['dataUrl']))
                                    <img src="{{ $f['dataUrl'] }}" alt="{{ $f['classname'] }}" class="max-h-12 max-w-12" />
                                @else
                                    <span class="text-[10px] text-gray-400">no icon</span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium" title="{{ $f['display_name'] ?? '' }}">{{ $f['display_name'] ?? $f['classname'] }}</p>
                                <p class="truncate text-xs text-gray-500" title="{{ $f['classname'] }}">{{ $f['classname'] }}</p>
                                <p class="truncate text-xs text-gray-400">{{ $f['caption'] ?? '' }} · id {{ $f['id'] ?? '-' }}</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <x-filament::button size="sm" color="gray" wire:click="mountAction('editFurni', { classname: @js($f['classname']) })">
                                Edit
                            </x-filament::button>
                            <x-filament::button size="sm" color="danger" wire:click="mountAction('deleteFurni', { classname: @js($f['classname']) })">
                                Delete
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($data['pages'] > 1)
                <div class="mt-4 flex items-center justify-between">
                    <x-filament::button size="sm" color="gray" wire:click="prevPage" :disabled="$data['page'] <= 1">
                        Previous
                    </x-filament::button>
                    <span class="text-sm text-gray-500">Page {{ $data['page'] }} of {{ $data['pages'] }}</span>
                    <x-filament::button size="sm" color="gray" wire:click="nextPage" :disabled="$data['page'] >= $data['pages']">
                        Next
                    </x-filament::button>
                </div>
            @endif
        @endif
    </x-filament::section>

    {{-- Edit/delete job status, mirroring the importer's terminal panel. --}}
    @php($status = $this->getJobStatus())
    @if ($status)
        @php($state = $status['state'] ?? 'queued')
        @php($terminal = in_array($state, ['done', 'error'], true))

        <div @if (! $terminal) wire:poll.3s @endif>
            <x-filament::section>
                <x-slot name="heading">
                    Status
                    <span @class([
                        'ml-2 text-sm font-medium',
                        'text-warning-600 dark:text-warning-400' => ! $terminal,
                        'text-success-600 dark:text-success-400' => $state === 'done',
                        'text-danger-600 dark:text-danger-400' => $state === 'error',
                    ])>
                        {{ strtoupper($state) }}{{ isset($status['phase']) ? ' · ' . $status['phase'] : '' }}
                    </span>
                </x-slot>

                @if (! empty($status['message']))
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $status['message'] }}</p>
                @endif

                @if (! empty($status['log']))
                    <div class="mt-4 overflow-hidden rounded-xl border border-gray-800 shadow-inner" style="background:#0b0f17">
                        <div class="flex items-center gap-2 border-b border-gray-800 px-3 py-2" style="background:#11161f">
                            <span class="h-3 w-3 rounded-full" style="background:#ff5f56"></span>
                            <span class="h-3 w-3 rounded-full" style="background:#ffbd2e"></span>
                            <span class="h-3 w-3 rounded-full" style="background:#27c93f"></span>
                            <span class="ml-2 font-mono text-xs text-gray-400">importer-worker</span>
                        </div>
                        <div
                            wire:key="mgr-log-{{ strlen($status['log']) }}"
                            x-data
                            x-init="$el.scrollTop = $el.scrollHeight"
                            class="max-h-72 overflow-auto px-4 py-3 font-mono text-xs leading-relaxed"
                        >
                            @foreach (preg_split('/\r?\n/', $status['log']) as $line)
                                @php($lc = strtolower($line))
                                @php($color = (str_contains($lc, 'error') || str_contains($lc, 'fail') || str_contains($lc, 'warn'))
                                    ? 'color:#fb7185'
                                    : ((str_contains($lc, 'done') || str_contains($lc, 'pushed') || str_contains($lc, 'updated') || str_contains($lc, 'deleted'))
                                        ? 'color:#34d399'
                                        : 'color:#cbd5e1'))
                                <div class="whitespace-pre-wrap" style="{{ $color }}">{{ $line }}</div>
                            @endforeach
                            @unless ($terminal)
                                <span class="mt-1 inline-block h-4 w-2 animate-pulse align-middle" style="background:#34d399"></span>
                            @endunless
                        </div>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
