@props(['handled' => []])

@php
    // Field-level messages are rendered under their own input, so the banner is
    // only for what has no field to sit under: throttling, the registration
    // guards (max accounts per IP, registration closed), and the terms box.
    $banner = collect($errors->keys())
        ->reject(fn (string $key): bool => in_array($key, $handled, true))
        ->flatMap(fn (string $key): array => $errors->get($key))
        ->all();
@endphp

@if (session('success'))
    <div class="pixel-alert pixel-alert--success" role="status">
        {{ session('success') }}
    </div>
@endif

{{-- 'message' is the site-wide failure flash; 'error' is the two-factor flow's. --}}
@foreach (array_filter([session('message'), session('error')]) as $flash)
    <div class="pixel-alert" role="alert">
        {{ $flash }}
    </div>
@endforeach

@if ($banner !== [])
    <div class="pixel-alert" role="alert">
        <ul>
            @foreach ($banner as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
