@props(['title', 'sub' => null])

<div {{ $attributes->merge(['class' => 'pt-card w-full max-w-md']) }}>
    <div class="pt-card-header">
        <h2>{{ $title }}</h2>
        @if ($sub)
            <p>{{ $sub }}</p>
        @endif
    </div>
    <div class="pt-card-body">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="pt-card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
