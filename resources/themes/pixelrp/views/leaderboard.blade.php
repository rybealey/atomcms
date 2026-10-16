@php
    $format = function (object $category, int $value): string {
        return match ($category->unit) {
            'dollars' => '$'.number_format($value),
            'hours' => number_format($value).' h',
            'level' => __('Lv :level', ['level' => $value]),
            default => number_format($value),
        };
    };
    $groups = $categories->groupBy('group');
@endphp

<x-app-layout active="leaderboard">
    @push('title', __('Leaderboard'))

    <div class="pg-head">
        <div class="pg-head-text">
            <h1 class="t-h1">{{ __('Leaderboard') }}</h1>
            <p class="t-body muted">{{ __("The city's top 10 in every category, and where you stand.") }}</p>
        </div>
    </div>

    <div class="set" data-cats-root>
        <nav class="lb-cats" aria-label="{{ __('Leaderboard categories') }}">
            <div class="lb-cats-scroll">
                @foreach ($groups as $group => $items)
                    <div class="lb-group stack g2">
                        <p class="t-over subtle">{{ $group }}</p>
                        <ul>
                            @foreach ($items as $category)
                                <li><button type="button" aria-pressed="{{ $loop->parent->first && $loop->first ? 'true' : 'false' }}" data-cat="{{ $category->id }}">{{ $category->label }}</button></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        <div class="set-body">
            @foreach ($categories as $category)
                @php
                    $podium = $category->entries->take(3);
                    $rest = $category->entries->slice(3);
                    $inTop = $category->entries->contains(fn ($entry) => (int) $entry->user->id === (int) $me->id);
                @endphp
                <section class="prp-card" aria-labelledby="lb-title-{{ $category->id }}" data-cat-panel="{{ $category->id }}" @if (! $loop->first) hidden @endif>
                    <div class="prp-card-head">
                        <div class="prp-card-titles">
                            <p class="t-over muted">{{ $category->group }}</p>
                            <h2 id="lb-title-{{ $category->id }}" class="t-h2">{{ $category->title }}</h2>
                        </div>
                    </div>

                    @if ($category->entries->isEmpty())
                        <div class="prp-card-body"><p class="t-sm muted">{{ __('Nobody is on this board yet.') }}</p></div>
                    @else
                        <ol class="lb-podium">
                            @foreach ($podium as $index => $entry)
                                @php($you = (int) $entry->user->id === (int) $me->id)
                                <li @class(['lb-pod', 'first' => $index === 0])>
                                    <span class="prp-tag {{ $index === 0 ? 'prp-tag-brand' : 'prp-tag-neutral' }}">#{{ $index + 1 }}</span>
                                    <x-prp.figure :look="$entry->user->look" size="m" />
                                    <span class="t-label-md lb-pod-name">{{ $entry->user->username }}</span>
                                    @if ($you)
                                        <span class="prp-tag prp-tag-brand">{{ __('You') }}</span>
                                    @endif
                                    <span class="t-num-md">{{ $format($category, $entry->value) }}</span>
                                </li>
                            @endforeach
                        </ol>

                        @if ($rest->isNotEmpty())
                            <ol class="prp-board-list lb-rest" start="4">
                                @foreach ($rest as $index => $entry)
                                    @php($you = (int) $entry->user->id === (int) $me->id)
                                    <li @class(['prp-board-row', 'me' => $you]) @if ($you) aria-current="true" @endif>
                                        <span class="prp-board-rank" style="font-weight: 500">{{ $index + 1 }}</span>
                                        <x-prp.head :look="$entry->user->look" :px="32" />
                                        <span class="prp-board-name">{{ $entry->user->username }}</span>
                                        @if ($you)
                                            <span class="prp-tag prp-tag-brand">{{ __('You') }}</span>
                                        @endif
                                        <span class="prp-board-value">{{ $format($category, $entry->value) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    @endif

                    @if (! $inTop)
                        <p class="t-over subtle" style="margin: 4px 24px 6px">{{ __('Your rank') }}</p>
                        <div class="row lb-me">
                            <span class="t-num" style="width: 32px; text-align: center; font-weight: 600">{{ number_format($category->me->rank) }}</span>
                            <x-prp.head :look="$me->look" :px="32" style="background: var(--surface)" />
                            <span class="grow t-label-md ink">{{ __('You, :name', ['name' => $me->username]) }}</span>
                            <span class="t-num ink">{{ $format($category, $category->me->value) }}</span>
                        </div>
                    @else
                        <div style="height: 4px"></div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
