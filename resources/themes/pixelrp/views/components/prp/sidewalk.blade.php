@props(['walkers' => []])

@php
    // Players in the city right now, drawn walking by the hotel's own imager:
    // four crisp walk frames per walker, stepped by the stylesheet, crossing
    // the foreground road at the bottom of the hotel view. Direction 1 walks
    // right, 5 left. Nobody is in view when the page loads: each walker waits
    // off the edge for its delay, then steps in.
    $durations = [34, 38, 31, 29, 27, 36, 32, 35];
    $delays = [3, 9, 16, 24, 33, 43, 54, 66];
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
