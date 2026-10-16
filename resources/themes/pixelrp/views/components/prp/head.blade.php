@props(['look', 'px' => 32, 'alt' => ''])

{{--
    A player's head in a round frame. The imager's head-only render is cropped
    to its content, so a hat or big hair moves the face down the image and a
    fixed crop clips it. The full figure is used instead: its feet sit on the
    bottom edge whatever the look, and the face is a fixed height above them,
    so the frame is centred on the face and scaled to its own size (1:1 at
    40px). The stylesheet does the placing from --head-px and --head-k.
--}}
<span {{ $attributes->class('head')->style(sprintf('--head-px: %dpx; --head-k: %s', $px, rtrim(rtrim(number_format($px / 36, 3, '.', ''), '0'), '.'))) }}>
    <img src="{{ \App\Support\Imager::body($look, 'm') }}" alt="{{ $alt }}" loading="lazy" decoding="async">
</span>
