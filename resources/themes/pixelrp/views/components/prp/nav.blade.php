@props(['active' => ''])

@guest
    {{-- Public pages (the rules) get the brand, the online count and a way in. --}}
    <a class="skip" href="#main">{{ __('Skip to content') }}</a>
    <header class="an">
        <div class="prp-nav an-in">
            <a class="wm prp-nav-brand" href="{{ route('welcome') }}" aria-label="{{ __(':hotel home', ['hotel' => setting('hotel_name')]) }}">
                <img class="an-wm" src="{{ asset('assets/images/pixelrp/wordmark.png') }}" width="117" height="32" alt="">
                <img class="an-em" src="{{ asset('assets/images/pixelrp/emblem.png') }}" width="18" height="32" alt="">
            </a>
            <div class="prp-nav-end">
                <span class="an-online">
                    <span class="prp-dot prp-dot-online pulse"></span>
                    <span class="t-num ink">{{ number_format(players_online()) }}</span> {{ __('online') }}
                </span>
                @if (setting('disable_registration') !== '1')
                    <a class="prp-btn prp-btn-ghost hide-sm" href="{{ route('register') }}">{{ __('Create an account') }}</a>
                @endif
                <a class="prp-btn prp-btn-primary" href="{{ route('welcome') }}">{{ __('Log in') }}</a>
            </div>
        </div>
    </header>
@else
@php
    $user = auth()->user();
    $characters = $user->characters();
    $me = $user->activeCharacter();
    $profile = app(\App\Services\Roleplay\PlayerProfile::class);
    $slotsLeft = max(0, 3 - $characters->count());
    $items = [
        'home' => ['route' => 'me.show', 'icon' => 'home', 'label' => __('Home')],
        'corporations' => ['route' => 'corporations.index', 'icon' => 'briefcase', 'label' => __('Corporations')],
        'gangs' => ['route' => 'gangs.index', 'icon' => 'shield', 'label' => __('Gangs')],
        'leaderboard' => ['route' => 'leaderboard.index', 'icon' => 'trophy', 'label' => __('Leaderboard')],
    ];
    $themes = [
        'dark' => ['icon' => 'moon', 'label' => __('Dark theme')],
        'light' => ['icon' => 'sun', 'label' => __('Light theme')],
        'system' => ['icon' => 'monitor', 'label' => __('Match my device')],
    ];
@endphp

<a class="skip" href="#main">{{ __('Skip to content') }}</a>

<header class="an">
    <div class="prp-nav an-in">
        <a class="wm prp-nav-brand" href="{{ route('me.show') }}" aria-label="{{ __(':hotel home', ['hotel' => setting('hotel_name')]) }}">
            <img class="an-wm" src="{{ asset('assets/images/pixelrp/wordmark.png') }}" width="117" height="32" alt="">
            <img class="an-em" src="{{ asset('assets/images/pixelrp/emblem.png') }}" width="18" height="32" alt="">
        </a>

        <nav aria-label="{{ __('Main') }}" style="height: 100%">
            <ul class="prp-nav-items an-links">
                @foreach ($items as $key => $item)
                    <li class="prp-nav-item">
                        <a class="prp-nav-link" href="{{ route($item['route']) }}" @if ($active === $key) aria-current="page" @endif>
                            <x-prp.icon :name="$item['icon']" />{{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="prp-nav-end">
            <span class="an-online">
                <span class="prp-dot prp-dot-online pulse"></span>
                <span class="t-num ink">{{ number_format(players_online()) }}</span> {{ __('online') }}
            </span>

            <a class="prp-btn prp-btn-primary an-enter-lg" href="{{ route('nitro-client') }}">{{ __('Enter City') }}</a>
            <a class="prp-btn prp-btn-primary prp-btn-sm an-enter-sm" href="{{ route('nitro-client') }}">{{ __('Enter') }}</a>

            <div style="position: relative">
                <button type="button" class="an-me" aria-expanded="false" aria-controls="an-menu" data-menu-toggle>
                    <x-prp.head :look="$me->look" :px="34" />
                    <span class="an-me-name" aria-hidden="true">{{ $me->username }}</span>
                    <span class="sr-only">{{ __('Account menu for :name', ['name' => $me->username]) }}</span>
                    <x-prp.icon name="chevron-down" class="ico-sm" />
                </button>

                <div id="an-menu" class="prp-menu an-menu" hidden>
                    <div class="an-menu-head">
                        <p class="t-label" style="margin: 0">{{ $me->username }}</p>
                        <p class="t-sm muted">{{ $profile->roleLine($me) }}</p>
                    </div>

                    <div class="an-menu-group" role="group" aria-labelledby="an-chars">
                        <p class="t-over subtle" id="an-chars" style="padding: 0 12px 6px">{{ __('Switch character') }}</p>

                        @foreach ($characters as $character)
                            @php($current = (int) $character->id === (int) $me->id)
                            <form method="POST" action="{{ route('characters.switch', $character) }}">
                                @csrf
                                <button type="submit" class="an-char" aria-pressed="{{ $current ? 'true' : 'false' }}" @disabled($current)>
                                    <x-prp.head :look="$character->look" :px="30" />
                                    <span class="stack grow g0" style="text-align: left">
                                        <span class="t-label-md">{{ $character->username }}</span>
                                        <span class="t-sm muted">{{ $profile->roleLine($character) }}</span>
                                    </span>
                                    @if ($current)
                                        <x-prp.icon name="check" stroke="2" class="ico-sm brand-ink" />
                                    @endif
                                </button>
                            </form>
                        @endforeach

                        @if ($slotsLeft > 0)
                            <a href="{{ route('nitro-client') }}" class="an-char-new" title="{{ __('New characters are created in the city, from the Wallet app on your phone.') }}">
                                <span class="an-char-plus" aria-hidden="true"><x-prp.icon name="plus" class="ico-sm" /></span>
                                <span class="grow">{{ __('New character') }}</span>
                                <span class="t-sm muted">{{ trans_choice(':count slot left|:count slots left', $slotsLeft, ['count' => $slotsLeft]) }}</span>
                            </a>
                        @endif
                    </div>

                    <a href="{{ route('settings.session-logs') }}">{{ __('Sessions') }}</a>
                    <a href="{{ route('settings.account.show') }}">{{ __('Account Settings') }}</a>
                    @if (hasPermission('housekeeping_access'))
                        <a href="{{ setting('housekeeping_url') }}" target="_blank" rel="noopener">{{ __('Housekeeping') }}</a>
                    @endif

                    <div class="an-menu-sep"></div>

                    <div class="an-menu-foot">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="prp-menu-item" style="padding: 6px 10px">{{ __('Log Out') }}</button>
                        </form>

                        <div class="seg theme-seg" role="group" aria-label="{{ __('Theme') }}">
                            @foreach ($themes as $key => $theme)
                                <button type="button" aria-pressed="false" aria-label="{{ $theme['label'] }}" title="{{ $theme['label'] }}" data-theme-pick="{{ $key }}">
                                    <x-prp.icon :name="$theme['icon']" class="ico-sm" />
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<nav class="an-tabs" aria-label="{{ __('Main') }}">
    @foreach ($items as $key => $item)
        <a href="{{ route($item['route']) }}" @if ($active === $key) aria-current="page" @endif>
            <x-prp.icon :name="$item['icon']" class="ico" />{{ $item['label'] }}
        </a>
    @endforeach
</nav>
@endguest
