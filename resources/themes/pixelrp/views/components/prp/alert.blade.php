@props(['handled' => [], 'flash' => true, 'validation' => true])

@php
    // Field-level messages render under their own input, so the banner is
    // only for what has no field to sit under: throttling, registration
    // guards, Fortify's own login failures.
    $banner = $validation
        ? collect($errors->keys())
            ->reject(fn (string $key): bool => in_array($key, $handled, true))
            ->flatMap(fn (string $key): array => $errors->get($key))
            ->all()
        : [];
@endphp

@if ($flash && session('success'))
    <div class="prp-alert prp-alert-success" role="status">
        <x-prp.icon name="check-circle" class="prp-alert-icon" />
        <div>{{ session('success') }}</div>
    </div>
@endif

@if ($flash)
    {{-- 'message' is the site-wide failure flash; 'error' is the two-factor flow's. --}}
    @foreach (array_filter([session('message'), session('error')]) as $message)
        <div class="prp-alert prp-alert-danger" role="alert">
            <x-prp.icon name="alert-circle" class="prp-alert-icon" />
            <div>{{ $message }}</div>
        </div>
    @endforeach
@endif

@if ($banner !== [])
    <div class="prp-alert prp-alert-danger" role="alert">
        <x-prp.icon name="alert-circle" class="prp-alert-icon" />
        <div>
            @if (isset($title))
                <span class="prp-alert-title">{{ $title }}</span>
            @endif
            @if (count($banner) === 1)
                {{ $banner[0] }}
            @else
                <ul>
                    @foreach ($banner as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
