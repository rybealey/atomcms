@php
    $disconnectedTitle = __('Disconnected from :hotel', ['hotel' => setting('hotel_name')]);
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="system">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ setting('hotel_name') }}: San Francisco</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@500&display=swap">

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('prp-theme');
                if (theme === 'light' || theme === 'dark' || theme === 'system') {
                    document.documentElement.setAttribute('data-theme', theme);
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/themes/pixelrp/css/app.css', 'resources/themes/pixelrp/js/app.js'], 'build')

    <style>
        html, body { height: 100%; overflow: hidden; }
        #nitro { position: absolute; inset: 0; width: 100%; height: 100%; margin: 0; padding: 0; border: 0; }
        #disconnected { position: absolute; inset: 0; z-index: 10; }
        #disconnected .auth { min-height: 100%; height: 100%; }
    </style>
</head>

<body>
    <iframe id="nitro" src="{{ sprintf('%s/index.html?sso=%s', setting('nitro_path'), $sso) }}" title="{{ setting('hotel_name') }}"></iframe>

    {{--
        Shown when the client reports that its connection closed. The reason
        comes from the client (RpDisconnectReasonMessages): the server says why
        before it closes the line, so a second tab, an hour idle and a restart
        each get their own words instead of all reading as "Connection lost".
    --}}
    <div id="disconnected" class="prp" hidden>
        <div class="auth">
            <x-prp.street-scene :sky="$sky" :walkers="$walkers" />
            <div class="dc-overlay" aria-hidden="true"></div>

            <header class="auth-top">
                <span class="chip" style="margin-left: auto">
                    <span class="prp-dot prp-dot-online pulse"></span>
                    <span class="t-num">{{ number_format(players_online()) }}</span> {{ __('online') }}
                </span>
            </header>

            <main class="dc-center" id="main">
                <img class="logo-hero" src="{{ asset('assets/images/pixelrp/logo-hero.gif') }}" width="201" height="55" alt="{{ setting('hotel_name') }}">

                <section class="auth-card" role="alert" aria-labelledby="dc-title" aria-describedby="dc-body" style="text-align: center; align-items: center">
                    <span class="dc-icon" id="dc-icon" aria-hidden="true">
                        <x-prp.icon name="wifi-off" class="ico-xl" data-dc-icon="wifi-off" />
                        <x-prp.icon name="monitor" class="ico-xl" data-dc-icon="monitor" hidden />
                        <x-prp.icon name="refresh" class="ico-xl" data-dc-icon="refresh" hidden />
                        <x-prp.icon name="clock" class="ico-xl" data-dc-icon="clock" hidden />
                    </span>

                    <div class="stack g2" style="align-items: center">
                        <p class="t-over muted" id="dc-eyebrow">{{ __('Disconnected') }}</p>
                        <h1 id="dc-title" class="t-h1">{{ __('Connection lost') }}</h1>
                        <p id="dc-body" class="t-body muted" style="max-width: 340px">{{ __('Your connection to the city dropped. Reconnect to pick up where you left off.') }}</p>
                    </div>

                    <div class="stack g3" style="width: 100%">
                        <button type="button" class="prp-btn prp-btn-primary prp-btn-lg prp-btn-block" onclick="reloadClient()">
                            <x-prp.icon name="refresh" class="ico-sm" />
                            <span id="dc-primary">{{ __('Reconnect') }}</span>
                        </button>
                        <a class="prp-btn prp-btn-ghost prp-btn-lg prp-btn-block" href="{{ route('me.show') }}">{{ __('Back to website') }}</a>
                    </div>

                    <div class="dc-meta t-sm muted">
                        <span>{{ __('Disconnected at') }} <span class="t-num" id="dc-time"></span> · <span class="t-num" id="dc-code">{{ __('Connection error') }}</span></span>
                        <a class="link" href="{{ setting('discord_invitation_link') }}" target="_blank" rel="noopener">{{ __('Get help on Discord') }}</a>
                    </div>
                </section>
            </main>

            <footer class="auth-foot">
                <button type="button" class="chip chip-btn focusable" aria-pressed="false" data-scene-pause
                    data-pause-label="{{ __('Pause street') }}" data-play-label="{{ __('Play street') }}">
                    <x-prp.icon name="pause" class="ico-sm" data-scene-icon="pause" />
                    <x-prp.icon name="play" class="ico-sm" data-scene-icon="play" hidden />
                    <span data-scene-label>{{ __('Pause street') }}</span>
                </button>
                <p class="auth-foot-note">
                    {{ __(':hotel is a not-for-profit educational project.', ['hotel' => setting('hotel_name')]) }}
                    <a class="focusable" href="{{ route('help-center.rules.index') }}">{{ __('Terms') }}</a> ·
                    <a class="focusable" href="{{ route('help-center.rules.index') }}#privacy">{{ __('Privacy') }}</a>
                </p>
            </footer>
        </div>
    </div>

    <script>
        // /game re-runs and mints a fresh ticket for whoever the account is
        // playing, so reconnecting is a reload of this page, never the frame.
        function reloadClient() {
            window.location.href = window.location;
        }

        (function () {
            var hotel = @json(setting('hotel_name'));
            var reasons = {
                'connection.lost': {
                    tone: '', icon: 'wifi-off', blink: true,
                    eyebrow: @json(__('Disconnected')),
                    title: @json(__('Connection lost')),
                    body: @json(__('Your connection to the city dropped. Reconnect to pick up where you left off.')),
                    primary: @json(__('Reconnect')),
                    code: @json(__('Connection error'))
                },
                'client.init.handshake.fail': {
                    tone: '', icon: 'wifi-off', blink: true,
                    eyebrow: @json(__('Disconnected')),
                    title: @json(__("Couldn't reach the city")),
                    body: @json(__('The city did not accept this connection. Try again in a moment, or go back to the website and enter again.')),
                    primary: @json(__('Try again')),
                    code: @json(__('Handshake failed'))
                },
                'session.replaced': {
                    tone: 'info', icon: 'monitor', blink: false,
                    eyebrow: @json(__('Signed in elsewhere')),
                    title: @json(__('You opened the city somewhere else')),
                    body: @json(__('Your account connected from another tab or device, so this window was closed. Reconnect here to move your session back.')),
                    primary: @json(__('Use this window')),
                    code: @json(__('Session replaced'))
                },
                'server.restart': {
                    tone: 'warn', icon: 'refresh', blink: true,
                    eyebrow: @json(__('Maintenance')),
                    title: @json(__('The city is restarting')),
                    body: @json(__("We're applying an update. Give it a minute, then reconnect.")),
                    primary: @json(__('Reconnect')),
                    code: @json(__('Server restart'))
                },
                'idle.timeout': {
                    tone: 'info', icon: 'clock', blink: false,
                    eyebrow: @json(__('Away')),
                    title: @json(__('You were away for a while')),
                    body: @json(__("We closed your connection after a long stretch without activity. Reconnect whenever you're ready.")),
                    primary: @json(__('Reconnect')),
                    code: @json(__('Idle timeout'))
                }
            };

            var overlay = document.getElementById('disconnected');
            var shown = false;

            function text(id, value) {
                var node = document.getElementById(id);
                if (node) node.textContent = value;
            }

            function show(reasonString) {
                if (shown) return;
                shown = true;

                var reason = reasons[reasonString] || reasons['connection.lost'];
                var icon = document.getElementById('dc-icon');

                icon.className = 'dc-icon ' + reason.tone + (reason.blink ? ' is-blink' : '');
                icon.querySelectorAll('[data-dc-icon]').forEach(function (svg) {
                    // inline SVG has no .hidden property; toggle the attribute
                    svg.toggleAttribute('hidden', svg.getAttribute('data-dc-icon') !== reason.icon);
                });

                text('dc-eyebrow', reason.eyebrow);
                text('dc-title', reason.title);
                text('dc-body', reason.body);
                text('dc-primary', reason.primary);
                text('dc-code', reason.code);

                try {
                    text('dc-time', new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', timeZone: 'America/Los_Angeles' }).format(new Date()));
                } catch (e) {}

                document.title = @json($disconnectedTitle);
                overlay.hidden = false;

                var frame = document.getElementById('nitro');
                if (frame) frame.setAttribute('aria-hidden', 'true');
            }

            // The client talks to this page through Nitro's legacy external
            // interface: a postMessage carrying a method name and arguments.
            window.FlashExternalInterface = window.FlashExternalInterface || {};
            window.FlashExternalInterface.disconnect = function (reasonCode, reasonString) {
                show(reasonString);
            };

            var frame = document.getElementById('nitro');
            var key = 'Nitro_LegacyExternalInterface';

            window.addEventListener('message', function (event) {
                if (!frame || event.source !== frame.contentWindow) return;
                if (typeof event.data !== 'string' || !event.data.startsWith(key)) return;

                var payload;
                try { payload = JSON.parse(event.data.substr(key.length)); } catch (e) { return; }

                var fn = window.FlashExternalInterface[payload.method];
                if (typeof fn === 'function') fn.apply(null, payload.params || []);
            });
        })();
    </script>
</body>

</html>
