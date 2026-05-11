<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>PixelRP</title>

    <link href="https://fonts.googleapis.com/css2?family=Ubuntu+Condensed&display=swap" rel="stylesheet">

    @vite(['resources/themes/' .  setting('theme') . '/css/app.css', 'resources/themes/' .  setting('theme') . '/js/app.js'], 'build')
</head>

<body class="overflow-hidden" id="nitro-client">
{{-- Pixelrp-specific wrapper for the Nitro client popout. Mirrors the atom
     theme's nitro.blade.php behaviour: iframes the Nitro client and listens
     for room-name postMessages so the browser tab reads "PixelRP ~ <room>".
     Lives here (rather than relying on theme fallback) so this view is
     served whatever the configured parent theme is — atom, dusk, or none.
--}}
<iframe id="nitro" src="{{ sprintf('%s/index.html?sso=%s', setting('nitro_path'), $sso) }}"
        class="absolute top-0 left-0 m-0 h-full w-full overflow-hidden border-none p-0"></iframe>

{{-- Disconnected fallback — shown if the iframe drops its socket. --}}
<div id="disconnected" class="h-screen w-full" style="display: none;">
    <div class="absolute h-full w-full bg-black bg-opacity-50"></div>

    <div class="relative flex h-full w-full flex-col items-center justify-center gap-4">
        <h2 class="text-2xl text-white">
            {{ __('Whoops! It seems like you have been disconnected...') }}
        </h2>

        <div class="flex gap-x-4">
            <button onclick="reloadClient()" class="pt-btn pt-btn--primary">
                {{ __('Reload client') }}
            </button>

            <a href="{{ route('me.show') }}" class="pt-btn pt-btn--secondary">
                {{ __('Back to website') }}
            </a>
        </div>
    </div>
</div>

<script>
    function toggleFullscreen() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
            return;
        }
        document.documentElement.requestFullscreen();
    }

    function reloadClient() {
        window.location.href = window.location;
    }

    // Title sync. Nitro (the iframe, patched by nitro-patches/230_rpWindowTitle)
    // posts {type:'pixeltower:title', room} on every room enter and exit. We
    // reflect it in the browser tab as "PixelRP ~ <room>" (or just "PixelRP"
    // when no room is active). Same protocol as the atom theme's blade so
    // either entrypoint produces identical tab titles.
    (function () {
        var BASE = 'PixelRP';
        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin) return;
            var data = event.data;
            if (!data || data.type !== 'pixeltower:title') return;
            var room = typeof data.room === 'string' ? data.room.trim() : '';
            document.title = room ? BASE + ' ~ ' + room : BASE;
        });
    })();
</script>

</body>

</html>
