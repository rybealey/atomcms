{{--
    The signed-in page frame: sticky app nav, a centred column of content and
    the site footer. Pages pass `active` so the nav can mark where you are.
--}}
@props(['active' => ''])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="system">

<head>
    <x-prp.document-head />
</head>

<body>
    <div class="prp pg">
        <div class="pg-nav">
            <x-prp.nav :active="$active" />
        </div>

        <main id="main" class="pg-main">
            <x-prp.alert :validation="false" />

            {{ $slot }}
        </main>

        <x-prp.footer />
    </div>

    @stack('javascript')
</body>

</html>
