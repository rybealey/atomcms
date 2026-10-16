@php
    $badgePath = rtrim((string) setting('group_badge_path'), '/');
    $palette = ['#4a0b3d', '#a3451c', '#27648a', '#5f4655', '#1f7a35', '#7a403b', '#6d1057', '#2d3a5c'];
    $colorFor = fn (int $id): string => $palette[$id % count($palette)];
@endphp

<x-app-layout active="gangs">
    @push('title', __('Gangs'))

    <div data-sort-root class="stack g8">
        <div class="pg-head">
            <div class="pg-head-text">
                <h1 class="t-h1">{{ __('Gangs') }}</h1>
                <p class="t-body muted">{{ __('Who runs which block.') }}</p>
            </div>
            <div class="row wrap g3">
                <span class="t-sm muted" id="gg-sort">{{ __('Rank by') }}</span>
                <div class="seg" role="group" aria-labelledby="gg-sort">
                    <button type="button" aria-pressed="true" data-sort="turf" data-sort-label="{{ __('turf held') }}">{{ __('Turf') }}</button>
                    <button type="button" aria-pressed="false" data-sort="level" data-sort-label="{{ __('level') }}">{{ __('Level') }}</button>
                </div>
            </div>
        </div>

        <div class="grid">
            <section class="prp-card s8" aria-labelledby="gg-stand">
                <div class="prp-card-head">
                    <div class="prp-card-titles">
                        <h2 id="gg-stand" class="prp-card-title">{{ __('Standings') }}</h2>
                        <p class="prp-card-sub">{{ trans_choice(':count gang|:count gangs', $gangs->count(), ['count' => $gangs->count()]) }}, {{ __('ranked by') }} <span data-sort-label>{{ __('turf held') }}</span></p>
                    </div>
                </div>
                <div class="tbl-box">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 40px">#</th>
                                <th scope="col">{{ __('Gang') }}</th>
                                <th scope="col">{{ __('Leader') }}</th>
                                <th scope="col" class="num">{{ __('Members') }}</th>
                                <th scope="col" class="num">{{ __('Turf') }}</th>
                                <th scope="col" class="num">{{ __('Level') }}</th>
                            </tr>
                        </thead>
                        <tbody data-sort-body>
                            @forelse ($gangs as $gang)
                                <tr @class(['me' => $mine !== null && $mine->id === $gang->id]) data-sort-row data-turf="{{ $gang->turf }}" data-level="{{ $gang->level * 100000 + $gang->xp }}" data-tiebreak="{{ $gang->members }}">
                                    <td class="t-num {{ $gang->rank === 1 ? 'brand-ink' : 'subtle' }}" data-rank>{{ $gang->rank }}</td>
                                    <td>
                                        <span class="row" style="gap: 10px">
                                            <span class="badge badge-sm" style="background: {{ $colorFor($gang->id) }}" aria-hidden="true">
                                                @if ($gang->badge !== '')
                                                    <img src="{{ $badgePath }}/{{ $gang->badge }}.png" alt="" loading="lazy" onerror="this.remove()">
                                                @else
                                                    {{ $gang->initials }}
                                                @endif
                                            </span>
                                            <span style="font-weight: 500">{{ $gang->name }}</span>
                                            @if ($mine !== null && $mine->id === $gang->id)
                                                <span class="prp-tag prp-tag-brand">{{ __('Yours') }}</span>
                                            @elseif ($gang->recruiting)
                                                <span class="prp-tag prp-tag-accent">{{ __('Recruiting') }}</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        <span class="row g2">
                                            @if ($gang->leader_look !== '')
                                                <x-prp.head :look="$gang->leader_look" :px="28" />
                                            @endif
                                            {{ $gang->leader !== '' ? $gang->leader : '—' }}
                                        </span>
                                    </td>
                                    <td class="num">{{ $gang->members }}</td>
                                    <td class="num">{{ $gang->turf }}</td>
                                    <td class="num">{{ $gang->level }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="muted">{{ __('No gangs have been founded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="s4 stack g6">
                @if ($mine !== null)
                    <section class="prp-card" aria-labelledby="gg-yours">
                        <div class="prp-card-head">
                            <span class="badge badge-md" style="background: {{ $colorFor($mine->id) }}" aria-hidden="true">
                                @if ($mine->badge !== '')
                                    <img src="{{ $badgePath }}/{{ $mine->badge }}.png" alt="" loading="lazy" onerror="this.remove()">
                                @else
                                    {{ $mine->initials }}
                                @endif
                            </span>
                            <div class="prp-card-titles">
                                <h2 id="gg-yours" class="prp-card-title">{{ $mine->name }}</h2>
                                <p class="prp-card-sub">{{ __('Your gang') }} · {{ __('You are :role', ['role' => $mine->role]) }}</p>
                            </div>
                        </div>
                        <div class="prp-card-body stack g4">
                            <div class="kv">
                                <div class="kv-cell"><span class="t-sm muted">{{ __('Standing') }}</span><span class="t-num-md">#{{ $mine->rank }} {{ __('of') }} {{ $mine->total }}</span></div>
                                <div class="kv-cell"><span class="t-sm muted">{{ __('Gap to #1') }}</span><span class="t-num-md">{{ $gapToFirst === 0 ? __('Top spot') : trans_choice(':count block|:count blocks', $gapToFirst, ['count' => $gapToFirst]) }}</span></div>
                            </div>
                            <p class="t-sm muted">{{ __('Level :level with :online of :members members in the city right now.', ['level' => $mine->level, 'online' => $mine->online, 'members' => $mine->members]) }}</p>
                        </div>
                    </section>
                @else
                    <section class="prp-card" aria-labelledby="gg-join">
                        <div class="prp-card-head">
                            <span class="prp-card-icon prp-card-icon-accent"><x-prp.icon name="shield" /></span>
                            <div class="prp-card-titles">
                                <h2 id="gg-join" class="prp-card-title">{{ __('Not in a gang') }}</h2>
                                <p class="prp-card-sub">{{ __('Gangs are joined and founded in game') }}</p>
                            </div>
                        </div>
                        <div class="prp-card-body stack g3">
                            <p class="t-sm muted">{{ __('Ask a gang that is recruiting for an invite, or found your own from the Gang window in the city.') }}</p>
                            <a class="prp-btn prp-btn-primary" href="{{ route('nitro-client') }}">{{ __('Enter City') }}</a>
                        </div>
                    </section>
                @endif

                <section class="prp-card" aria-labelledby="gg-turf">
                    <div class="prp-card-head">
                        <div class="prp-card-titles">
                            <h2 id="gg-turf" class="prp-card-title">{{ __('Who holds what') }}</h2>
                            <p class="prp-card-sub">{{ trans_choice(':count district|:count districts', $territories->count(), ['count' => $territories->count()]) }}</p>
                        </div>
                    </div>
                    @if ($territories->isEmpty())
                        <div class="prp-card-body"><p class="t-sm muted">{{ __('No block is held right now. The city is up for grabs.') }}</p></div>
                    @else
                        <ul class="divide list-reset">
                            @foreach ($territories as $turf)
                                <li class="list-row list-row-tight">
                                    <span style="width: 10px; height: 10px; border-radius: 3px; flex: none; background: {{ $colorFor($turf->gang_id) }}" aria-hidden="true"></span>
                                    <span class="grow t-label-md">{{ $turf->district }}</span>
                                    <span class="t-sm muted">{{ $turf->gang }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
