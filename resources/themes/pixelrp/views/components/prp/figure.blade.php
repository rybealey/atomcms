@props(['look', 'size' => 'm', 'direction' => 2, 'alt' => ''])

{{-- A full-body figure from the imager. Sizes: s (32px wide), m (64px), l (128px). --}}
<img {{ $attributes->class('figure') }} src="{{ \App\Support\Imager::body($look, $size, (int) $direction) }}"
    alt="{{ $alt }}" loading="lazy" decoding="async">
