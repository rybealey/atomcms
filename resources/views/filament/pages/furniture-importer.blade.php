<x-filament-panels::page>
    {{ $this->form }}

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
                                    <td class="py-1 pr-4">{{ $item['classname'] ?? '—' }}</td>
                                    <td class="py-1 pr-4">{{ $item['id'] ?? '—' }}</td>
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

                @if (! empty($status['log']))
                    <pre class="mt-4 max-h-64 overflow-auto rounded-md bg-gray-950 p-3 text-xs leading-relaxed text-gray-200">{{ $status['log'] }}</pre>
                @endif

                @if ($state === 'done')
                    <p class="mt-4 text-sm text-success-600 dark:text-success-400">
                        Pushed to main — the production deploy is running. Furni will be live (catalog · PixelRP · your username) once the deploy finishes and players reload the client.
                    </p>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
