@php
    $icon = match ($corporation->service_type) {
        'medical' => 'medical',
        'police' => 'star',
        'staff' => 'flag',
        default => 'briefcase',
    };
    $hq = $corporation->hq_rooms->first();
@endphp

<x-app-layout active="corporations">
    @push('title', $corporation->name)

    <nav aria-label="{{ __('Breadcrumb') }}">
        <ol class="crumbs t-sm">
            <li><a class="link" href="{{ route('corporations.index') }}">{{ __('Corporations') }}</a></li>
            <li aria-hidden="true" class="subtle">/</li>
            <li aria-current="page" class="muted">{{ $corporation->name }}</li>
        </ol>
    </nav>

    <section class="prp-card" aria-labelledby="cd-title">
        <div class="row wrap row-start" style="padding: 24px; gap: 20px">
            <x-prp.corp-badge :corporation="$corporation" size="xl" />

            <div class="stack g3 grow" style="flex: 1 1 360px">
                <div class="row wrap g2">
                    @if ($corporation->on_duty > 0)
                        <span class="prp-tag prp-tag-accent">{{ __('Open now') }}</span>
                    @endif
                    @if ($corporation->service_type !== '')
                        <span class="prp-tag prp-tag-neutral">{{ __(ucfirst($corporation->service_type)) }}</span>
                    @endif
                    @if ($job !== null)
                        <span class="prp-tag prp-tag-brand">{{ __('You work here') }}</span>
                    @endif
                </div>
                <h1 id="cd-title" class="t-h1">{{ $corporation->name }}</h1>
                @if ($corporation->description !== '')
                    <p class="t-body muted" style="max-width: 640px">{{ $corporation->description }}</p>
                @endif
                <div class="row wrap t-sm muted" style="gap: 8px 20px">
                    @if ($corporation->head !== null)
                        <span class="row g2">
                            <x-prp.head :look="$corporation->head->look" :px="24" />
                            {{ __('Run by') }} <strong class="ink" style="font-weight: 500">{{ $corporation->head->username }}</strong>
                            <span class="subtle">· {{ $corporation->head_rank }}</span>
                        </span>
                    @endif
                    @if ($corporation->acronym !== '')
                        <span>{{ $corporation->acronym }}</span>
                    @endif
                    @if ($hq !== null)
                        <span>{{ __('HQ at') }} <span class="ink">{{ $hq->name }}</span></span>
                    @endif
                </div>
            </div>

            <a class="prp-btn prp-btn-primary prp-btn-lg" href="{{ route('nitro-client') }}">{{ $hq !== null ? __('Go to HQ in game') : __('Enter City') }}</a>
        </div>

        <div class="grid" style="gap: 1px; background: var(--line); border-top: 1px solid var(--line)">
            <div class="s3 stack" style="background: var(--surface); padding: 16px 24px; gap: 2px"><span class="t-sm muted">{{ __('Employees') }}</span><span class="t-num-lg">{{ $corporation->employees }}</span></div>
            <div class="s3 stack" style="background: var(--surface); padding: 16px 24px; gap: 2px"><span class="t-sm muted">{{ __('On shift now') }}</span><span class="t-num-lg">{{ $corporation->on_duty }}</span></div>
            <div class="s3 stack" style="background: var(--surface); padding: 16px 24px; gap: 2px"><span class="t-sm muted">{{ __('Positions') }}</span><span class="t-num-lg">{{ $corporation->positions }}</span></div>
            <div class="s3 stack" style="background: var(--surface); padding: 16px 24px; gap: 2px"><span class="t-sm muted">{{ __('Starting pay') }}</span><span class="t-num-lg">${{ number_format($corporation->starting_pay) }} <span class="t-sm muted" style="font-family: var(--font-sans)">{{ __('per shift') }}</span></span></div>
        </div>
    </section>

    <div class="grid">
        <section class="prp-card s8" aria-labelledby="cd-pos">
            <div class="prp-card-head">
                <div class="prp-card-titles">
                    <h2 id="cd-pos" class="prp-card-title">{{ __('Positions') }}</h2>
                    <p class="prp-card-sub">{{ __('Highest rank first. Pay is per 10-minute shift.') }}</p>
                </div>
            </div>
            <div class="tbl-box">
                <table class="tbl tbl-narrow">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Position') }}</th>
                            <th scope="col" class="num">{{ __('Pay') }}</th>
                            <th scope="col" class="num">{{ __('Staff') }}</th>
                            <th scope="col">{{ __('Who') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($corporation->ranks as $index => $rank)
                            @php($mine = $job !== null && $rank->name === $job->rank)
                            <tr @class(['me' => $mine])>
                                <td>
                                    <span class="row" style="gap: 10px">
                                        <span class="t-num subtle" style="width: 16px">{{ $index + 1 }}</span>
                                        <span style="font-weight: 500">{{ $rank->name }}</span>
                                        @if ($mine)
                                            <span class="prp-tag prp-tag-brand">{{ __('You') }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="num">${{ number_format($rank->pay) }}</td>
                                <td class="num">{{ $rank->count }}</td>
                                <td>
                                    <span class="row g0">
                                        <span class="head-stack">
                                            @foreach ($rank->employees->take(3) as $employee)
                                                <x-prp.head :look="$employee->look" :px="26" :alt="$employee->username" />
                                            @endforeach
                                        </span>
                                        @if ($rank->count > 3)
                                            <span class="t-sm muted" style="margin-left: 10px">+{{ $rank->count - 3 }}</span>
                                        @elseif ($rank->count === 0)
                                            <span class="t-sm subtle">{{ __('Open') }}</span>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">{{ __('No positions have been set up yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="s4 stack g6">
            <section class="prp-card" aria-labelledby="cd-shift">
                <div class="prp-card-head">
                    <div class="prp-card-titles">
                        <h2 id="cd-shift" class="prp-card-title">{{ __('On shift now') }}</h2>
                        <p class="prp-card-sub">
                            {{ trans_choice(':count working|:count working', $corporation->on_duty, ['count' => $corporation->on_duty]) }}
                            @if ($hq !== null) {{ __('at') }} {{ $hq->name }} @endif
                        </p>
                    </div>
                </div>
                @if ($corporation->on_shift->isEmpty())
                    <div class="prp-card-body"><p class="t-sm muted">{{ __('Nobody is clocked in right now.') }}</p></div>
                @else
                    <ul class="prp-board-list">
                        @foreach ($corporation->on_shift as $employee)
                            <li class="prp-board-row">
                                <x-prp.head :look="$employee->look" :px="32" />
                                <span class="prp-board-name">{{ $employee->username }}</span>
                                <span class="t-sm muted">{{ $employee->rank_name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="prp-card" aria-labelledby="cd-apply">
                <div class="prp-card-head">
                    <div class="prp-card-titles"><h2 id="cd-apply" class="prp-card-title">{{ __('How to apply') }}</h2></div>
                </div>
                <ol class="prp-card-body stack g3" style="margin: 0; padding-left: 40px">
                    <li class="t-body">{{ $hq !== null ? __('Enter :hotel and walk to :room.', ['hotel' => setting('hotel_name'), 'room' => $hq->name]) : __('Enter :hotel and find the corporation\'s HQ.', ['hotel' => setting('hotel_name')]) }}</li>
                    <li class="t-body">{{ __('Ask a manager for an interview.') }}</li>
                    <li class="t-body">{{ __('Once you are hired, clock in at HQ to start earning.') }}</li>
                </ol>
            </section>
        </div>
    </div>
</x-app-layout>
