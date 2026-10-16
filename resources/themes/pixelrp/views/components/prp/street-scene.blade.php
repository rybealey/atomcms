@props(['sky', 'walkers' => []])

@php
    /** @var \App\Data\CitySky $sky */
    $phase = $sky->phase;
    $weather = $sky->weather;
    $overcast = in_array($weather, ['rain', 'cloudy'], true);

    // Palettes from the design's StreetScene: sky bands (top to bottom), the
    // far hills and skyline, the bay, fog, clouds, the bridge, and the colour
    // filter the foreground houses take at each time of day.
    $palettes = [
        'night' => ['b' => ['#110a24', '#170f30', '#1e153c', '#271c49', '#302456'], 'hill' => '#1c1430', 'city' => '#231a3b', 'water' => '#151030', 'waterHi' => '#3a3260', 'fog' => '#5f5a78', 'cloud' => '#3a3158', 'bridge' => '#5a2420', 'filter' => 'url(#prp-dim-night)', 'walk' => 'url(#prp-dim-walk)', 'sun' => [0, 0], 'sunFill' => '#f3ead8', 'winFar' => '#f5c542'],
        'dawn' => ['b' => ['#3b3a6e', '#6a5a8e', '#bd8a96', '#f0a07a', '#ffd9b0'], 'hill' => '#5a4a6e', 'city' => '#6a5a7e', 'water' => '#7f7095', 'waterHi' => '#e0b8b8', 'fog' => '#ece2e4', 'cloud' => '#f6dcd2', 'bridge' => '#a8402c', 'filter' => 'url(#prp-dim-dawn)', 'walk' => 'none', 'sun' => [52, 112], 'sunFill' => '#ffe9c9', 'winFar' => '#ffe1b0'],
        'day' => ['b' => ['#4f9fdc', '#66ade2', '#7fbbe8', '#99c9ee', '#b7d9f3'], 'hill' => '#7c93a6', 'city' => '#8fa3b5', 'water' => '#4a86b5', 'waterHi' => '#9cc8e6', 'fog' => '#eef1f3', 'cloud' => '#ffffff', 'bridge' => '#c4442e', 'filter' => 'none', 'walk' => 'none', 'sun' => [376, 20], 'sunFill' => '#fff4c2', 'winFar' => '#ffffff'],
        'dusk' => ['b' => ['#2a1640', '#4a0b3d', '#8a2a62', '#d0566a', '#f0954a'], 'hill' => '#3d2042', 'city' => '#4a2a4c', 'water' => '#5a2a50', 'waterHi' => '#e08a6a', 'fog' => '#c4a8b4', 'cloud' => '#e88a7a', 'bridge' => '#8a2e24', 'filter' => 'url(#prp-dim-dusk)', 'walk' => 'none', 'sun' => [418, 124], 'sunFill' => '#ffd27a', 'winFar' => '#f5b96a'],
    ];
    $overcastBands = [
        'night' => ['#16131f', '#1c1826', '#221e2e', '#2a2536', '#322c3f'],
        'dawn' => ['#5a5868', '#706b7c', '#8a8290', '#a49aa4', '#bdb2b8'],
        'day' => ['#8e9aa6', '#9ba6b1', '#a8b2bc', '#b6bec7', '#c4cbd2'],
        'dusk' => ['#3a2a3c', '#4e3646', '#664452', '#80545e', '#9a6a6c'],
    ];

    $p = $palettes[$phase];
    $b = $overcast ? $overcastBands[$phase] : $p['b'];
    $cloud = $overcast ? ($phase === 'night' ? '#3a3448' : ($phase === 'day' ? '#d6dbe0' : '#9a8e98')) : $p['cloud'];
    $fgFilter = $overcast && $phase === 'day' ? 'url(#prp-dim-gray)' : $p['filter'];
    $d = fn (bool $show): string => $show ? 'inline' : 'none';
    $lit = $d($phase !== 'day');
    $stars = $d($phase === 'night' && ! $overcast);
    $sun = $d($phase !== 'night' && ! $overcast);
    $clouds = $d($overcast || ($weather === 'clear' && $phase !== 'night'));
    $isFog = $d($weather === 'fog');
    $isRain = $d($weather === 'rain');

    // Walkers: real players, rendered walking by the imager. Alternate the
    // direction and spread the timings so the street never looks looped.
    $durations = [30, 34, 29, 26, 24, 32, 28, 31];
    $delays = [-2, -20, -15, -9, -13, -27, -6, -18];
@endphp

<div {{ $attributes->class(['scene', "ph-{$phase}"]) }} data-scene aria-hidden="true" style="background: {{ $b[0] }}">
    <svg viewBox="0 0 480 270" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
        <defs>
            <filter id="prp-dim-night" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0.40 0 0 0 0  0 0.40 0 0 0  0 0 0.56 0 0.02  0 0 0 1 0"></feColorMatrix></filter>
            <filter id="prp-dim-dusk" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0.86 0 0 0 0  0 0.62 0 0 0  0 0 0.72 0 0  0 0 0 1 0"></feColorMatrix></filter>
            <filter id="prp-dim-dawn" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0.88 0 0 0 0  0 0.78 0 0 0  0 0 0.86 0 0  0 0 0 1 0"></feColorMatrix></filter>
            <filter id="prp-dim-gray" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0.78 0.04 0.04 0 0  0.04 0.78 0.04 0 0  0.05 0.05 0.82 0 0  0 0 0 1 0"></feColorMatrix></filter>
            <filter id="prp-dim-walk" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0.70 0 0 0 0  0 0.66 0 0 0  0 0 0.80 0 0  0 0 0 1 0"></feColorMatrix></filter>
            <pattern id="prp-sw" x="0" y="214" width="16" height="18" patternUnits="userSpaceOnUse"><rect width="16" height="18" fill="#b9aea6"></rect><rect width="1" height="18" fill="#a0948b"></rect><rect width="16" height="1" fill="#cfc4bb"></rect></pattern>
            <pattern id="prp-dash" x="0" y="247" width="28" height="2" patternUnits="userSpaceOnUse"><rect width="14" height="2" fill="#d8cfc2"></rect></pattern>

            <g id="vic">
                <polygon class="vr" points="0,33 25,4 50,33"></polygon>
                <polygon class="vt" points="7,31 25,11 43,31"></polygon>
                <polygon fill="currentColor" points="11,31 25,15 39,31"></polygon>
                <rect class="vt" x="22" y="20" width="6" height="7"></rect>
                <rect class="vg" x="23" y="21" width="4" height="5"></rect>
                <rect fill="currentColor" x="2" y="31" width="46" height="69"></rect>
                <rect class="vt" x="0" y="30" width="50" height="3"></rect>
                <rect class="vt" x="5" y="37" width="22" height="27"></rect>
                <rect class="vg" x="7" y="39" width="4" height="22"></rect>
                <rect class="vg" x="14" y="39" width="4" height="22"></rect>
                <rect class="vg" x="21" y="39" width="4" height="22"></rect>
                <rect class="vt" x="3" y="63" width="26" height="3"></rect>
                <rect class="vt" x="5" y="69" width="22" height="22"></rect>
                <rect class="vg" x="7" y="71" width="4" height="18"></rect>
                <rect class="vg" x="14" y="71" width="4" height="18"></rect>
                <rect class="vg" x="21" y="71" width="4" height="18"></rect>
                <rect fill="#8a7d78" x="2" y="91" width="28" height="9"></rect>
                <rect fill="#6f6460" x="8" y="94" width="5" height="3"></rect>
                <rect class="vt" x="32" y="38" width="12" height="22"></rect>
                <rect class="vg" x="34" y="40" width="8" height="18"></rect>
                <rect class="vt" x="31" y="66" width="14" height="3"></rect>
                <rect class="vg" x="33" y="69" width="10" height="2"></rect>
                <rect class="vt" x="32" y="71" width="12" height="21"></rect>
                <rect class="vd" x="34" y="73" width="8" height="19"></rect>
                <rect fill="#cfc4bb" x="31" y="92" width="16" height="2"></rect>
                <rect fill="#bdb1a7" x="30" y="94" width="18" height="2"></rect>
                <rect fill="#cfc4bb" x="29" y="96" width="20" height="2"></rect>
                <rect fill="#bdb1a7" x="28" y="98" width="22" height="2"></rect>
            </g>
            <g id="vic-lit-a">
                <rect class="lit" x="7" y="39" width="4" height="22"></rect>
                <rect class="lit" x="14" y="39" width="4" height="22"></rect>
                <rect class="lit" x="34" y="40" width="8" height="18"></rect>
                <rect class="lit" x="33" y="69" width="10" height="2"></rect>
            </g>
            <g id="vic-lit-b">
                <rect class="lit" x="14" y="71" width="4" height="18"></rect>
                <rect class="lit" x="21" y="71" width="4" height="18"></rect>
                <rect class="lit" x="23" y="21" width="4" height="5"></rect>
            </g>
            <g id="shop">
                <rect fill="#8a4a44" x="0" y="26" width="50" height="74"></rect>
                <rect fill="#7a403b" x="4" y="34" width="6" height="1"></rect>
                <rect fill="#7a403b" x="28" y="44" width="8" height="1"></rect>
                <rect fill="#7a403b" x="14" y="52" width="6" height="1"></rect>
                <rect fill="#7a403b" x="40" y="30" width="6" height="1"></rect>
                <rect fill="#3d2634" x="-1" y="22" width="52" height="5"></rect>
                <rect fill="#f3e6d0" x="6" y="31" width="12" height="18"></rect>
                <rect class="vg" x="8" y="33" width="8" height="14"></rect>
                <rect fill="#f3e6d0" x="32" y="31" width="12" height="18"></rect>
                <rect class="vg" x="34" y="33" width="8" height="14"></rect>
                <rect fill="#2b1723" x="8" y="51" width="34" height="5"></rect>
                <rect fill="#f5f0e8" x="-2" y="58" width="54" height="7"></rect>
                <rect fill="#d62a86" x="-2" y="58" width="6" height="7"></rect>
                <rect fill="#d62a86" x="10" y="58" width="6" height="7"></rect>
                <rect fill="#d62a86" x="22" y="58" width="6" height="7"></rect>
                <rect fill="#d62a86" x="34" y="58" width="6" height="7"></rect>
                <rect fill="#d62a86" x="46" y="58" width="6" height="7"></rect>
                <rect fill="#b81e74" x="-2" y="65" width="54" height="1"></rect>
                <rect fill="#3d2634" x="3" y="69" width="30" height="23"></rect>
                <rect class="vg" x="5" y="71" width="26" height="19"></rect>
                <rect fill="#3d2634" x="37" y="69" width="10" height="31"></rect>
                <rect fill="#2d3a5c" x="38" y="70" width="8" height="30"></rect>
                <rect fill="#5f4655" x="0" y="92" width="36" height="8"></rect>
            </g>
            <g id="shop-lit">
                <rect class="lit" x="5" y="71" width="26" height="19"></rect>
                <rect class="lit" x="8" y="33" width="8" height="14"></rect>
                <rect fill="#f5c542" x="12" y="53" width="26" height="1"></rect>
            </g>
            <g id="tree">
                <rect fill="#5a3b2a" x="9" y="38" width="3" height="20"></rect>
                <rect fill="#2f6b45" x="3" y="12" width="15" height="26"></rect>
                <rect fill="#2f6b45" x="1" y="18" width="19" height="16"></rect>
                <rect fill="#3d8a58" x="5" y="6" width="11" height="10"></rect>
                <rect fill="#3d8a58" x="4" y="14" width="6" height="6"></rect>
                <rect fill="#3d8a58" x="12" y="20" width="6" height="5"></rect>
                <rect fill="#24543a" x="3" y="32" width="15" height="6"></rect>
                <rect fill="#8a7d78" x="6" y="57" width="9" height="3"></rect>
            </g>
            <g id="lamp">
                <rect fill="#2b1723" x="2" y="8" width="2" height="56"></rect>
                <rect fill="#2b1723" x="0" y="4" width="6" height="4"></rect>
                <rect fill="#f3e6d0" x="1" y="8" width="4" height="2"></rect>
                <rect fill="#2b1723" x="1" y="62" width="4" height="2"></rect>
            </g>
            <g id="cloud">
                <rect x="6" y="0" width="10" height="3"></rect>
                <rect x="2" y="3" width="20" height="3"></rect>
                <rect x="0" y="6" width="28" height="4"></rect>
            </g>
            <g id="raindrops" fill="#d6def2" opacity="0.5">
                <rect x="12" y="8" width="1" height="6"></rect><rect x="44" y="40" width="1" height="6"></rect><rect x="70" y="120" width="1" height="6"></rect><rect x="96" y="22" width="1" height="6"></rect>
                <rect x="130" y="180" width="1" height="6"></rect><rect x="150" y="70" width="1" height="6"></rect><rect x="176" y="140" width="1" height="6"></rect><rect x="204" y="12" width="1" height="6"></rect>
                <rect x="226" y="96" width="1" height="6"></rect><rect x="250" y="210" width="1" height="6"></rect><rect x="268" y="50" width="1" height="6"></rect><rect x="292" y="160" width="1" height="6"></rect>
                <rect x="318" y="30" width="1" height="6"></rect><rect x="340" y="110" width="1" height="6"></rect><rect x="362" y="230" width="1" height="6"></rect><rect x="384" y="76" width="1" height="6"></rect>
                <rect x="408" y="190" width="1" height="6"></rect><rect x="430" y="18" width="1" height="6"></rect><rect x="452" y="132" width="1" height="6"></rect><rect x="474" y="60" width="1" height="6"></rect>
                <rect x="30" y="200" width="1" height="6"></rect><rect x="58" y="250" width="1" height="6"></rect><rect x="110" y="96" width="1" height="6"></rect><rect x="190" y="236" width="1" height="6"></rect>
                <rect x="240" y="150" width="1" height="6"></rect><rect x="330" y="200" width="1" height="6"></rect><rect x="396" y="140" width="1" height="6"></rect><rect x="444" y="244" width="1" height="6"></rect>
            </g>
        </defs>

        <rect x="0" y="0" width="480" height="40" fill="{{ $b[0] }}"></rect>
        <rect x="0" y="40" width="480" height="36" fill="{{ $b[1] }}"></rect>
        <rect x="0" y="76" width="480" height="30" fill="{{ $b[2] }}"></rect>
        <rect x="0" y="106" width="480" height="24" fill="{{ $b[3] }}"></rect>
        <rect x="0" y="130" width="480" height="40" fill="{{ $b[4] }}"></rect>

        <g display="{{ $stars }}" fill="#fbf5ef">
            <rect x="22" y="14" width="1" height="1"></rect><rect x="58" y="36" width="1" height="1" opacity="0.6"></rect><rect x="94" y="10" width="1" height="1"></rect>
            <rect x="132" y="48" width="1" height="1" opacity="0.6"></rect><rect x="168" y="22" width="1" height="1"></rect><rect x="210" y="8" width="1" height="1" opacity="0.6"></rect>
            <rect x="246" y="40" width="1" height="1"></rect><rect x="284" y="16" width="1" height="1" opacity="0.6"></rect><rect x="318" y="54" width="1" height="1"></rect>
            <rect x="352" y="12" width="1" height="1"></rect><rect x="440" y="46" width="1" height="1" opacity="0.6"></rect><rect x="466" y="20" width="1" height="1"></rect>
            <rect x="196" y="62" width="1" height="1" opacity="0.6"></rect><rect x="112" y="70" width="1" height="1" opacity="0.6"></rect><rect x="30" y="64" width="1" height="1" opacity="0.6"></rect>
        </g>
        <g display="{{ $sun }}" transform="translate({{ $p['sun'][0] }} {{ $p['sun'][1] }})" fill="{{ $p['sunFill'] }}">
            <rect x="3" y="0" width="6" height="1"></rect><rect x="1" y="1" width="10" height="2"></rect><rect x="0" y="3" width="12" height="6"></rect><rect x="1" y="9" width="10" height="2"></rect><rect x="3" y="11" width="6" height="1"></rect>
        </g>
        <g display="{{ $stars }}" transform="translate(404 22)">
            <rect x="2" y="0" width="6" height="1" fill="#f3ead8"></rect><rect x="1" y="1" width="8" height="1" fill="#f3ead8"></rect><rect x="0" y="2" width="10" height="6" fill="#f3ead8"></rect><rect x="1" y="8" width="8" height="1" fill="#f3ead8"></rect><rect x="2" y="9" width="6" height="1" fill="#f3ead8"></rect>
            <rect x="3" y="3" width="2" height="2" fill="#d8cfc2"></rect><rect x="6" y="6" width="1" height="1" fill="#d8cfc2"></rect>
        </g>
        <g display="{{ $clouds }}">
            <g class="drift" fill="{{ $cloud }}">
                <use href="#cloud" x="40" y="30"></use>
                <use href="#cloud" x="250" y="54"></use>
                <use href="#cloud" x="410" y="76"></use>
                <g display="{{ $d($overcast) }}">
                    <use href="#cloud" x="120" y="12"></use>
                    <use href="#cloud" x="180" y="40"></use>
                    <use href="#cloud" x="330" y="20"></use>
                    <use href="#cloud" x="-6" y="66"></use>
                    <use href="#cloud" x="440" y="36"></use>
                </g>
            </g>
        </g>

        <polygon fill="{{ $p['hill'] }}" points="0,160 0,122 18,122 18,114 36,114 36,106 52,106 52,100 74,100 74,106 92,106 92,113 106,113 106,105 122,105 122,111 140,111 140,118 160,118 160,160"></polygon>
        <g fill="{{ $p['hill'] }}">
            <rect x="62" y="74" width="2" height="26"></rect><rect x="57" y="80" width="12" height="1"></rect><rect x="58" y="88" width="10" height="1"></rect><rect x="60" y="70" width="6" height="4"></rect>
        </g>
        <g fill="{{ $p['city'] }}">
            <rect x="300" y="112" width="14" height="48"></rect><rect x="316" y="100" width="10" height="60"></rect><rect x="328" y="118" width="16" height="42"></rect>
            <polygon points="346,160 346,120 349,120 349,100 351,100 351,82 353,82 353,62 355,62 355,82 357,82 357,100 359,100 359,120 362,120 362,160"></polygon>
            <rect x="364" y="96" width="12" height="64"></rect><rect x="378" y="108" width="14" height="52"></rect><rect x="394" y="88" width="10" height="72"></rect><rect x="396" y="82" width="6" height="6"></rect>
            <rect x="406" y="116" width="18" height="44"></rect><rect x="426" y="102" width="12" height="58"></rect><rect x="440" y="120" width="20" height="40"></rect><rect x="462" y="108" width="18" height="52"></rect>
        </g>
        <g display="{{ $lit }}" fill="{{ $p['winFar'] }}">
            <rect x="303" y="118" width="1" height="1"></rect><rect x="308" y="126" width="1" height="1"></rect><rect x="319" y="108" width="1" height="1"></rect><rect x="322" y="122" width="1" height="1"></rect>
            <rect x="352" y="104" width="1" height="1"></rect><rect x="367" y="104" width="1" height="1"></rect><rect x="371" y="116" width="1" height="1"></rect><rect x="381" y="114" width="1" height="1"></rect>
            <rect x="397" y="96" width="1" height="1"></rect><rect x="400" y="110" width="1" height="1"></rect><rect x="410" y="124" width="1" height="1"></rect><rect x="430" y="110" width="1" height="1"></rect>
            <rect x="445" y="128" width="1" height="1"></rect><rect x="466" y="116" width="1" height="1"></rect><rect x="472" y="126" width="1" height="1"></rect>
        </g>

        <rect x="0" y="150" width="480" height="52" fill="{{ $p['water'] }}"></rect>
        <g fill="{{ $p['waterHi'] }}">
            <rect x="20" y="160" width="14" height="1"></rect><rect x="60" y="167" width="10" height="1"></rect><rect x="200" y="158" width="18" height="1"></rect><rect x="250" y="171" width="12" height="1"></rect>
            <rect x="300" y="162" width="16" height="1"></rect><rect x="420" y="168" width="14" height="1"></rect><rect x="160" y="178" width="20" height="1"></rect><rect x="352" y="182" width="10" height="1"></rect>
        </g>

        <g fill="{{ $p['bridge'] }}">
            <rect x="186" y="96" width="4" height="58"></rect><rect x="196" y="96" width="4" height="58"></rect>
            <rect x="186" y="104" width="14" height="2"></rect><rect x="186" y="118" width="14" height="2"></rect><rect x="186" y="132" width="14" height="2"></rect><rect x="185" y="94" width="16" height="2"></rect>
            <rect x="282" y="96" width="4" height="58"></rect><rect x="292" y="96" width="4" height="58"></rect>
            <rect x="282" y="104" width="14" height="2"></rect><rect x="282" y="118" width="14" height="2"></rect><rect x="282" y="132" width="14" height="2"></rect><rect x="281" y="94" width="16" height="2"></rect>
            <rect x="130" y="146" width="220" height="3"></rect>
            <rect x="210" y="128" width="1" height="18"></rect><rect x="226" y="136" width="1" height="10"></rect><rect x="242" y="139" width="1" height="7"></rect><rect x="258" y="136" width="1" height="10"></rect><rect x="272" y="128" width="1" height="18"></rect>
            <rect x="160" y="128" width="1" height="18"></rect><rect x="174" y="114" width="1" height="32"></rect><rect x="306" y="114" width="1" height="32"></rect><rect x="320" y="128" width="1" height="18"></rect>
        </g>
        <path d="M130 146 Q160 130 193 97 Q241 156 289 97 Q322 130 350 146" fill="none" stroke="{{ $p['bridge'] }}" stroke-width="1.4"></path>

        <g display="{{ $isFog }}">
            <g class="drift" fill="{{ $p['fog'] }}" opacity="0.88">
                <rect x="-40" y="140" width="560" height="62"></rect>
                <rect x="10" y="134" width="130" height="6"></rect>
                <rect x="150" y="124" width="60" height="16"></rect>
                <rect x="170" y="128" width="180" height="12"></rect>
                <rect x="260" y="120" width="44" height="8"></rect>
                <rect x="360" y="132" width="110" height="8"></rect>
            </g>
        </g>

        <g filter="{{ $fgFilter }}">
            <use href="#vic" x="0" y="114" class="h-sage"></use>
            <use href="#vic" x="50" y="114" class="h-blue"></use>
            <use href="#vic" x="100" y="114" class="h-mustard"></use>
            <rect x="150" y="202" width="180" height="12" fill="#8f8580"></rect>
            <rect x="150" y="200" width="180" height="2" fill="#a59a94"></rect>
            <g fill="#2b1723">
                <rect x="150" y="186" width="180" height="2"></rect>
                <rect x="150" y="188" width="2" height="12"></rect><rect x="165" y="188" width="2" height="12"></rect><rect x="180" y="188" width="2" height="12"></rect><rect x="195" y="188" width="2" height="12"></rect>
                <rect x="210" y="188" width="2" height="12"></rect><rect x="225" y="188" width="2" height="12"></rect><rect x="240" y="188" width="2" height="12"></rect><rect x="255" y="188" width="2" height="12"></rect>
                <rect x="270" y="188" width="2" height="12"></rect><rect x="285" y="188" width="2" height="12"></rect><rect x="300" y="188" width="2" height="12"></rect><rect x="315" y="188" width="2" height="12"></rect><rect x="328" y="188" width="2" height="12"></rect>
            </g>
            <use href="#vic" x="330" y="114" class="h-rose"></use>
            <use href="#shop" x="380" y="114"></use>
            <use href="#vic" x="430" y="114" class="h-lav"></use>
            <use href="#tree" x="154" y="154"></use>
            <use href="#tree" x="306" y="154"></use>
            <use href="#lamp" x="196" y="150"></use>
            <use href="#lamp" x="278" y="150"></use>
            <rect x="0" y="214" width="480" height="18" fill="url(#prp-sw)"></rect>
            <rect x="0" y="232" width="480" height="3" fill="#8f837b"></rect>
            <rect x="0" y="235" width="480" height="27" fill="#3a3440"></rect>
            <rect x="0" y="247" width="480" height="2" fill="url(#prp-dash)"></rect>
            <rect x="0" y="262" width="480" height="8" fill="#2b2530"></rect>
        </g>

        <g display="{{ $lit }}">
            <use href="#vic-lit-a" x="0" y="114"></use>
            <use href="#vic-lit-b" x="50" y="114"></use>
            <use href="#vic-lit-a" x="100" y="114"></use>
            <use href="#vic-lit-b" x="330" y="114"></use>
            <use href="#shop-lit" x="380" y="114"></use>
            <use href="#vic-lit-a" x="430" y="114"></use>
            <g fill="#f5c542">
                <rect x="197" y="158" width="4" height="2"></rect><rect x="279" y="158" width="4" height="2"></rect>
                <rect x="190" y="148" width="18" height="16" opacity="0.18"></rect><rect x="272" y="148" width="18" height="16" opacity="0.18"></rect>
                <rect x="184" y="142" width="30" height="28" opacity="0.08"></rect><rect x="266" y="142" width="30" height="28" opacity="0.08"></rect>
                <rect x="184" y="212" width="30" height="8" opacity="0.12"></rect><rect x="266" y="212" width="30" height="8" opacity="0.12"></rect>
            </g>
        </g>

        <g display="{{ $isFog }}">
            <rect x="0" y="100" width="480" height="170" fill="{{ $p['fog'] }}" opacity="0.16"></rect>
        </g>

        {{-- Players walking the sidewalk: the hotel's own imager, walk frames as a GIF. --}}
        <g filter="{{ $p['walk'] }}">
            @foreach (array_values($walkers) as $index => $look)
                @php
                    $left = $index % 2 === 1;
                    $lane = $index % 3 === 2 ? 186 : 180;
                @endphp
                <g class="walker {{ $left ? 'wl' : 'wr' }}" style="animation-duration: {{ $durations[$index % count($durations)] }}s; animation-delay: {{ $delays[$index % count($delays)] }}s">
                    <rect x="4" y="{{ $lane + 52 }}" width="24" height="2" fill="#000" opacity="0.18"></rect>
                    <image href="{{ \App\Support\Imager::walking($look, $left ? 6 : 2) }}" x="0" y="{{ $lane }}" width="32" height="55"
                        preserveAspectRatio="xMidYMax meet" style="image-rendering: pixelated"></image>
                </g>
            @endforeach
        </g>

        <g display="{{ $isRain }}">
            <g class="rain">
                <use href="#raindrops"></use>
                <use href="#raindrops" x="20" y="-270"></use>
            </g>
        </g>
    </svg>
</div>
