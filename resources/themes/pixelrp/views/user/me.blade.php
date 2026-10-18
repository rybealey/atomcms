@php
    $weatherIcon = match (true) {
        $sky->weather === 'rain' => 'cloud-rain',
        $sky->weather === 'fog' => 'cloud-fog',
        $sky->weather === 'cloudy' => 'cloud',
        $sky->phase === 'night' => 'moon',
        default => 'sun',
    };
    $lastOnline = $me->last_online > 0 ? \Carbon\Carbon::createFromTimestamp($me->last_online) : null;
    $joined = $me->account_created > 0 ? \Carbon\Carbon::createFromTimestamp($me->account_created) : null;
    $motto = trim((string) $me->motto);
@endphp

<x-app-layout active="home">
    @push('title', __('Home'))

    <div class="pg-head">
        <div class="pg-head-text">
            <h1 class="t-h1">{{ __('Home') }}</h1>
            <p class="t-body muted">{{ __('Your character, wallet and friends at a glance.') }}</p>
        </div>
    </div>

    <div class="grid">
        {{-- Hello --}}
        <section class="prp-card s8" aria-labelledby="hm-hello">
            <div class="row wrap g0" style="align-items: stretch">
                {{-- The player on the street from the login screen, tinted to the city's sky right now --}}
                <div class="hero-scene hero-{{ $sky->phase }} hero-{{ $sky->weather }}" aria-hidden="true">
                    <div class="hero-scene-art" style="background-image: url('{{ asset('assets/images/pixelrp/city.webp') }}')"></div>
                    <div class="hero-scene-tint"></div>
                    <span class="hero-scene-shadow"></span>
                    <x-prp.figure :look="$me->look" size="l" class="hero-figure" />
                </div>

                <div class="stack g4 grow card-pad" style="flex: 1 1 300px; padding: 24px">
                    <div class="stack g2">
                        <p class="t-over muted">{{ $sky->greeting($hour) }}</p>
                        <h2 id="hm-hello" class="t-h1">{{ $me->username }}</h2>
                        @if ($motto !== '')
                            <p class="t-body muted">{{ $motto }}</p>
                        @endif
                    </div>

                    <div class="row wrap g2">
                        @if ($me->online)
                            <span class="prp-tag prp-tag-success"><span class="prp-dot prp-dot-online"></span>{{ __('In the city') }}</span>
                        @else
                            <span class="prp-tag prp-tag-neutral"><span class="prp-dot"></span>{{ __('Offline') }}</span>
                        @endif
                        <span class="t-sm muted">
                            @if (! $me->online && $lastOnline !== null){{ __('Last seen :when', ['when' => $lastOnline->diffForHumans()]) }} · @endif
                            @if ($joined !== null){{ __('Migrated in :when', ['when' => $joined->format('M Y')]) }}@endif
                        </span>
                    </div>

                    <div class="row wrap g3" style="margin-top: auto">
                        <a class="prp-btn prp-btn-primary prp-btn-lg" href="{{ route('nitro-client') }}">
                            {{ __('Enter City') }}
                            <x-prp.icon name="arrow-right" class="ico-sm" />
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- Wallet --}}
        <section class="prp-card s4" aria-labelledby="hm-wallet">
            <div class="prp-card-head">
                <span class="prp-card-icon"><x-prp.icon name="wallet" /></span>
                <div class="prp-card-titles">
                    <h2 id="hm-wallet" class="prp-card-title">{{ __('Wallet') }}</h2>
                    <p class="prp-card-sub">{{ __('Your balances in the city') }}</p>
                </div>
            </div>
            <ul class="divide list-reset">
                <li class="list-row">
                    <span class="prp-currency-icon" style="background: var(--surface-sunken); width: 36px; height: 36px">
                        <img src="{{ asset('assets/images/icons/currency/cash.png') }}" width="15" height="15" alt="" style="width: 15px; height: 15px">
                    </span>
                    <span class="grow t-body">{{ __('Cash') }}</span>
                    <span class="t-num-lg">${{ number_format($wallet->cash) }}</span>
                </li>
                <li class="list-row">
                    <span class="prp-currency-icon" style="background: var(--surface-sunken); width: 36px; height: 36px; color: var(--ink-muted)">
                        <x-prp.icon name="bank" class="ico-sm" />
                    </span>
                    <span class="grow t-body">{{ __('Deposits') }}</span>
                    <span class="t-num-lg">${{ number_format($wallet->deposits) }}</span>
                </li>
                <li class="list-row">
                    <span class="prp-currency-icon" style="background: var(--surface-sunken); width: 36px; height: 36px">
                        <img src="{{ asset('assets/images/icons/currency/diamonds.png') }}" width="15" height="15" alt="" style="width: 15px; height: 15px">
                    </span>
                    <span class="grow t-body">{{ __('Diamonds') }}</span>
                    <span class="t-num-lg">{{ number_format($wallet->diamonds) }}</span>
                </li>
            </ul>
        </section>

        {{-- Job --}}
        <section class="prp-card s4" aria-labelledby="hm-job">
            <div class="prp-card-head">
                <span class="prp-card-icon"><x-prp.icon name="briefcase" /></span>
                <div class="prp-card-titles">
                    <h2 id="hm-job" class="prp-card-title">{{ __('Employment') }}</h2>
                    <p class="prp-card-sub">{{ $job?->corporation ?? __('Unemployed') }}</p>
                </div>
                @if ($job?->on_duty)
                    <span class="prp-tag prp-tag-success">{{ __('On shift') }}</span>
                @endif
            </div>
            <div class="prp-card-body stack g4">
                @if ($job !== null)
                    <div class="kv">
                        <div class="kv-cell"><span class="t-sm muted">{{ __('Position') }}</span><span class="t-label-lg">{{ $job->rank }}</span></div>
                        <div class="kv-cell"><span class="t-sm muted">{{ __('Pay per shift') }}</span><span class="t-num-md">${{ number_format($job->pay) }}</span></div>
                    </div>
                    <div class="stack g2">
                        <div class="row between">
                            <span class="t-sm">
                                @if ($job->next_rank !== null)
                                    {{ __('Next promotion: :rank', ['rank' => $job->next_rank]) }}
                                @else
                                    {{ __('Top of the ladder') }}
                                @endif
                            </span>
                            <span class="t-num muted">{{ __('Tier :tier / :tiers', ['tier' => $job->tier, 'tiers' => $job->tiers]) }}</span>
                        </div>
                        <div class="prog" role="progressbar" aria-label="{{ __('Tiers toward your next promotion') }}" aria-valuemin="0" aria-valuemax="{{ $job->tiers }}" aria-valuenow="{{ $job->tier }}">
                            <span style="width: {{ round($job->tier / $job->tiers * 100) }}%"></span>
                        </div>
                    </div>
                    <a class="link t-sm" href="{{ route('corporations.show', $job->corporation_id) }}">{{ __('View :corporation', ['corporation' => $job->corporation]) }}</a>
                @else
                    <p class="t-sm muted">{{ __('Every corporation in the city is hiring at its HQ. Pick one and ask for an interview.') }}</p>
                    <a class="link t-sm" href="{{ route('corporations.index') }}">{{ __('Browse corporations') }}</a>
                @endif
            </div>
        </section>

        {{-- Gang --}}
        <section class="prp-card s4" aria-labelledby="hm-gang">
            <div class="prp-card-head">
                <span class="prp-card-icon prp-card-icon-accent"><x-prp.icon name="shield" /></span>
                <div class="prp-card-titles">
                    <h2 id="hm-gang" class="prp-card-title">{{ __('Gang Affiliation') }}</h2>
                    <p class="prp-card-sub">{{ $gang?->name ?? __('No gang') }}</p>
                </div>
                @if ($gang !== null)
                    <span class="prp-tag prp-tag-accent">#{{ $gang->rank }}</span>
                @endif
            </div>
            <div class="prp-card-body stack g4">
                @if ($gang !== null)
                    <div class="kv">
                        <div class="kv-cell"><span class="t-sm muted">{{ __('Rank') }}</span><span class="t-label-lg">{{ $gang->role }}</span></div>
                        <div class="kv-cell"><span class="t-sm muted">{{ __('Turf held') }}</span><span class="t-num-md">{{ trans_choice(':count block|:count blocks', $gang->turf, ['count' => $gang->turf]) }}</span></div>
                    </div>
                    <div class="row g2">
                        <div class="head-stack">
                            @foreach ($gangMembers as $member)
                                <x-prp.head :look="$member->look" :px="28" :alt="$member->username" />
                            @endforeach
                        </div>
                        <span class="t-sm muted">{{ __(':online of :total members online', ['online' => $gang->online, 'total' => $gang->members]) }}</span>
                    </div>
                    <a class="link t-sm" href="{{ route('gangs.index') }}">{{ __('See gang standings') }}</a>
                @else
                    <p class="t-sm muted">{{ __('Gangs hold the blocks and run the night. Find one that is recruiting, or found your own in game.') }}</p>
                    <a class="link t-sm" href="{{ route('gangs.index') }}">{{ __('See gang standings') }}</a>
                @endif
            </div>
        </section>

        {{-- Stats --}}
        <section class="prp-card s4" aria-labelledby="hm-stats">
            <div class="prp-card-head">
                <span class="prp-card-icon"><x-prp.icon name="chart" /></span>
                <div class="prp-card-titles">
                    <h2 id="hm-stats" class="prp-card-title">{{ __('Statistics') }}</h2>
                    <p class="prp-card-sub">
                        {{ $stats->open_charges > 0 ? trans_choice(':count open charge|:count open charges', $stats->open_charges, ['count' => $stats->open_charges]) : __('Clean record') }}
                    </p>
                </div>
            </div>
            <div class="prp-card-body stack g4">
                <div class="stack g2">
                    <div class="row between"><span class="t-sm">{{ __('Health') }}</span><span class="t-num muted">{{ $stats->health }} / {{ $stats->health_max }}</span></div>
                    <div class="prog" role="progressbar" aria-label="{{ __('Health') }}" aria-valuemin="0" aria-valuemax="{{ $stats->health_max }}" aria-valuenow="{{ $stats->health }}">
                        <span style="width: {{ round(min(1, $stats->health / $stats->health_max) * 100) }}%"></span>
                    </div>
                </div>
                <div class="kv">
                    <div class="kv-cell"><span class="t-sm muted">{{ __('Hours online') }}</span><span class="t-num-md">{{ number_format($stats->online_hours) }}</span></div>
                    <div class="kv-cell"><span class="t-sm muted">{{ __('Hours on shift') }}</span><span class="t-num-md">{{ number_format($stats->shift_hours) }}</span></div>
                    <div class="kv-cell"><span class="t-sm muted">{{ __('Strength') }}</span><span class="t-num-md">{{ __('Lv :level', ['level' => $stats->strength]) }}</span></div>
                    <div class="kv-cell"><span class="t-sm muted">{{ __('Stamina') }}</span><span class="t-num-md">{{ __('Lv :level', ['level' => $stats->stamina]) }}</span></div>
                </div>
            </div>
        </section>

        {{-- Friends --}}
        <section class="prp-card s8" aria-labelledby="hm-friends">
            <div class="prp-card-head">
                <span class="prp-card-icon"><x-prp.icon name="users" /></span>
                <div class="prp-card-titles">
                    <h2 id="hm-friends" class="prp-card-title">{{ __('Friends in Town') }}</h2>
                    <p class="prp-card-sub">{{ __(':online of :total friends online', ['online' => $friendCounts['online'], 'total' => $friendCounts['total']]) }}</p>
                </div>
            </div>
            @if ($friends->isEmpty())
                <div class="prp-card-body">
                    <p class="t-sm muted">{{ $friendCounts['total'] === 0 ? __('No friends yet. Say hello to someone in the city.') : __('None of your friends are in the city right now.') }}</p>
                </div>
            @else
                <ul class="divide list-reset">
                    @foreach ($friends as $friend)
                        <li class="list-row">
                            <x-prp.head :look="$friend->look" :px="40" />
                            <div class="grow stack g0">
                                <span class="t-label-md">{{ $friend->username }}</span>
                                @if ($friend->motto !== '')
                                    <span class="t-sm muted">{{ $friend->motto }}</span>
                                @endif
                            </div>
                            <span class="prp-staff-status hide-sm"><span class="prp-dot prp-dot-online"></span>{{ __('Online') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- City --}}
        <section class="prp-card s4 self-start" aria-labelledby="hm-city">
            <div class="prp-card-head">
                <span class="prp-card-icon"><x-prp.icon name="pin" /></span>
                <div class="prp-card-titles">
                    <h2 id="hm-city" class="prp-card-title">San Francisco, CA</h2>
                    <p class="prp-card-sub">{{ __('San Francisco time and weather') }}</p>
                </div>
            </div>
            <div class="prp-card-body stack g4">
                <div class="row" style="gap: 14px">
                    <span aria-hidden="true" style="display: grid; place-items: center; flex: none; width: 56px; height: 56px; border-radius: 10px; background: var(--surface-sunken); color: var(--ink)">
                        <x-prp.icon :name="$weatherIcon" class="ico-xl" />
                    </span>
                    <div class="stack" style="gap: 2px">
                        <span class="t-num-lg" data-sf-time>{{ $sky->time }}</span>
                        <span class="t-body muted">{{ $sky->weatherLabel() }}@if ($sky->temperature !== null), {{ $sky->temperature }}°F @endif</span>
                    </div>
                </div>
                <div class="row g2">
                    <span class="prp-dot prp-dot-online"></span>
                    <span class="t-body"><span class="t-num">{{ number_format(players_online()) }}</span> {{ __('citizens online') }}</span>
                </div>
                <p class="t-sm muted">
                    @if ($sky->held)
                        {{ __('City staff are holding the sky right now, so the game and this page both show their weather.') }}
                    @else
                        {{ __('The sky in game follows the real city, so a foggy night here is a foggy night in :hotel.', ['hotel' => setting('hotel_name')]) }}
                    @endif
                </p>
            </div>
        </section>
    </div>
</x-app-layout>
