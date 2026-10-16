@props(['walkers' => []])

@php
    // Real players, drawn walking by the hotel's own imager: four crisp walk
    // frames per walker, stepped by the stylesheet, crossing the foreground
    // road at the bottom of the hotel view. Direction 1 walks right, 5 left.
    $durations = [34, 38, 31, 29, 27, 36, 32, 35];
    $delays = [-2, -20, -15, -9, -13, -27, -6, -18];
@endphp

<div class="sidewalk" aria-hidden="true">
    @foreach (array_values($walkers) as $index => $look)
        @php($left = $index % 2 === 1)
        <span class="sw-walker {{ $left ? 'sw-left' : 'sw-right' }} {{ $index % 3 === 2 ? 'sw-near' : '' }}"
            style="animation-duration: {{ $durations[$index % count($durations)] }}s; animation-delay: {{ $delays[$index % count($delays)] }}s">
            @for ($frame = 0; $frame < 4; $frame++)
                <img class="wf wf{{ $frame }}" src="{{ \App\Support\Imager::walkFrame($look, $frame, $left ? 5 : 1) }}" alt="" decoding="async">
            @endfor
        </span>
    @endforeach
</div>
