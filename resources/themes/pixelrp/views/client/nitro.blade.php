<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ setting('hotel_name') }} - Nitro</title>

    <link href="https://fonts.googleapis.com/css2?family=Ubuntu+Condensed&display=swap" rel="stylesheet">

    @livewireStyles
    @livewireScriptConfig
    @vite(['resources/themes/' .  setting('theme') . '/css/app.css', 'resources/themes/' .  setting('theme') . '/js/app.js'], 'build')
</head>

<body class="overflow-hidden" id="nitro-client">
    <iframe id="nitro" src="{{ sprintf('%s/index.html?sso=%s', setting('nitro_path'), $sso) }}"
        class="absolute top-0 left-0 m-0 h-full w-full overflow-hidden border-none p-0"></iframe>

    {{-- Show disconnected message on client if the user has been disconnected --}}
    <div id="disconnected" class="h-screen w-full">
        <div class="absolute h-full w-full bg-black/50"></div>

        <div class="relative flex h-full w-full flex-col items-center justify-center gap-4">
            <h2 class="text-2xl text-white">
                {{ __('Whoops! It seems like you have been disconnected...') }}
            </h2>

            <div class="flex gap-x-4">
                <button
                    class="py-2 px-4 text-white rounded bg-[#eeb425] hover:bg-[#e3aa1e] border-2 border-[#cf9d15] transition ease-in-out"
                    onclick="reloadClient()">
                    {{ __('Reload client') }}
                </button>

                <a href="{{ route('me.show') }}">
                    <x-form.secondary-button>
                        {{ __('Back to website') }}
                    </x-form.secondary-button>
                </a>
            </div>
        </div>
    </div>

    <script>
        function reloadClient() {
            window.location.href = window.location;
        }
    </script>

    <script src="{{ asset('assets/js/atom.js') }}"></script>
</body>

</html>
