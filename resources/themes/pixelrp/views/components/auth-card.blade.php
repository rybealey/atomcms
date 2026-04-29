@props(['title', 'sub' => null])

<div {{ $attributes->merge(['class' => 'pt-card w-[420px] max-w-full']) }}>
    <div class="pt-card-header pt-card-header--auth">
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
