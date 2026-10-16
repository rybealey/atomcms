@props(['look', 'px' => 32, 'alt' => ''])

{{-- A player's head, rendered by the hotel's own imager, in a round frame. --}}
<span {{ $attributes->class('head')->style("width: {$px}px; height: {$px}px") }}>
    <img src="{{ \App\Support\Imager::head($look, 'm') }}" alt="{{ $alt }}" loading="lazy" decoding="async">
</span>
