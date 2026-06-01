<x-filament-panels::page>
    {{ $this->form }}

    {{-- Per-row preview thumbnails, rendered by the importer-worker from the
         staged uploads. Polls every 2s while any are still rendering. --}}
    @php($previews = $this->previewStates())
    @if (! empty($previews))
        @php($previewsPending = collect($previews)->contains(fn ($p) => ($p['state'] ?? '') === 'pending'))
        <div @if ($previewsPending) wire:poll.2s @endif>
            <x-filament::section>
                <x-slot name="heading">Preview</x-slot>
                <x-slot name="description">Rendered straight from each uploaded file. Wall furni and brand-new uploads may take a moment.</x-slot>

                <div class="flex flex-wrap gap-4">
                    @foreach ($previews as $p)
                        <div class="flex w-28 flex-col items-center gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <div class="flex h-16 w-16 items-center justify-center" style="image-rendering: pixelated">
                                @if (($p['state'] ?? '') === 'ok')
                                    <img src="{{ $p['dataUrl'] }}" alt="{{ $p['filename'] }}" class="max-h-16 max-w-16" />
                                @elseif (($p['state'] ?? '') === 'pending')
                                    <span class="h-6 w-6 animate-spin rounded-full border-2 border-gray-300 border-t-primary-500"></span>
                                @else
                                    <span class="text-center text-xs text-danger-500" title="{{ $p['reason'] ?? '' }}">no preview</span>
                                @endif
                            </div>
                            <span class="w-full truncate text-center text-xs text-gray-500" title="{{ $p['filename'] }}">{{ $p['filename'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    @endif

    @php($status = $this->getJobStatus())

    @if ($status)
        @php($state = $status['state'] ?? 'queued')
        @php($terminal = in_array($state, ['done', 'error'], true))

        <div @if (! $terminal) wire:poll.3s @endif>
            <x-filament::section>
                <x-slot name="heading">
                    Import status
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

                @if (! empty($status['commit']))
                    <p class="mt-2 text-xs text-gray-500">commit {{ substr($status['commit'], 0, 12) }}</p>
                @endif

                @if (! empty($status['items']))
                    <table class="mt-4 w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-1 pr-4">File</th>
                                <th class="py-1 pr-4">Classname</th>
                                <th class="py-1 pr-4">ID</th>
                                <th class="py-1 pr-4">Result</th>
                                <th class="py-1">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($status['items'] as $item)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-1 pr-4">{{ $item['filename'] ?? '' }}</td>
                                    <td class="py-1 pr-4">{{ $item['classname'] ?? '-' }}</td>
                                    <td class="py-1 pr-4">{{ $item['id'] ?? '-' }}</td>
                                    <td @class([
                                        'py-1 pr-4 font-medium',
                                        'text-success-600 dark:text-success-400' => in_array($item['state'] ?? '', ['ok', 'reused'], true),
                                        'text-danger-600 dark:text-danger-400' => ($item['state'] ?? '') === 'failed',
                                    ])>{{ $item['state'] ?? '' }}</td>
                                    <td class="py-1 text-gray-500">{{ $item['reason'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                {{-- Modern terminal log view. --}}
                @if (! empty($status['log']))
                    <div class="mt-4 overflow-hidden rounded-xl border border-gray-800 shadow-inner" style="background:#0b0f17">
                        <div class="flex items-center gap-2 border-b border-gray-800 px-3 py-2" style="background:#11161f">
                            <span class="h-3 w-3 rounded-full" style="background:#ff5f56"></span>
                            <span class="h-3 w-3 rounded-full" style="background:#ffbd2e"></span>
                            <span class="h-3 w-3 rounded-full" style="background:#27c93f"></span>
                            <span class="ml-2 font-mono text-xs text-gray-400">importer-worker</span>
                        </div>
                        {{-- wire:key keyed on log length re-inits the scroller on
                             every poll so it sticks to the newest line. --}}
                        <div
                            wire:key="log-{{ strlen($status['log']) }}"
                            x-data
                            x-init="$el.scrollTop = $el.scrollHeight"
                            class="max-h-72 overflow-auto px-4 py-3 font-mono text-xs leading-relaxed"
                        >
                            @foreach (preg_split('/\r?\n/', $status['log']) as $line)
                                @php($lc = strtolower($line))
                                @php($color = (str_contains($lc, 'error') || str_contains($lc, 'fail') || str_contains($lc, 'warn'))
                                    ? 'color:#fb7185'
                                    : ((str_contains($lc, 'done') || str_contains($lc, 'pushed') || str_contains($lc, 'imported') || str_contains($lc, 'renamed') || str_contains($lc, '(ok'))
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

                @if ($state === 'done')
                    <p class="mt-4 text-sm text-success-600 dark:text-success-400">
                        Pushed to main. The production deploy is running. Furni will be live (catalog · PixelRP · your sub-page) once the deploy finishes and players reload the client.
                    </p>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
