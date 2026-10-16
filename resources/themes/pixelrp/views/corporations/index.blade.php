<x-app-layout active="corporations">
    @push('title', __('Corporations'))

    <div data-filter-root class="stack g8">
        <div class="pg-head">
            <div class="pg-head-text">
                <h1 class="t-h1">{{ __('Corporations') }}</h1>
                <p class="t-body muted">{{ __('Every job in the city. Find one that fits, then apply in game at its HQ.') }}</p>
            </div>
            <div class="row wrap g3">
                <div class="searchbox" style="width: 260px; max-width: 100%">
                    <x-prp.icon name="search" class="ico-sm" />
                    <label class="sr-only" for="corp-q">{{ __('Search corporations') }}</label>
                    <input class="prp-input" id="corp-q" type="search" placeholder="{{ __('Search corporations') }}" data-filter-search autocomplete="off">
                </div>
                <div class="seg" role="group" aria-label="{{ __('Filter') }}">
                    <button type="button" aria-pressed="true" data-filter="all">{{ __('All') }}</button>
                    <button type="button" aria-pressed="false" data-filter="open">{{ __('On shift now') }}</button>
                </div>
            </div>
        </div>

        <div class="row wrap g6">
            <p class="t-sm muted"><span class="t-num ink">{{ number_format($corporations->count()) }}</span> {{ __('corporations') }}</p>
            <p class="t-sm muted"><span class="t-num ink">{{ number_format($employees) }}</span> {{ __('employees') }}</p>
            <p class="t-sm muted"><span class="t-num ink">{{ number_format($openNow) }}</span> {{ __('with staff on shift') }}</p>
        </div>

        @if ($corporations->isEmpty())
            <div class="prp-card card-pad stack g2 empty">
                <h2 class="t-h3">{{ __('No corporations yet') }}</h2>
                <p class="t-sm muted">{{ __('The city is still being founded. Check back soon.') }}</p>
            </div>
        @endif

        <div class="prp-card card-pad stack g2 empty" data-filter-empty hidden>
            <h2 class="t-h3">{{ __('No corporations match') }} &ldquo;<span data-filter-query></span>&rdquo;</h2>
            <p class="t-sm muted">{{ __('Try a different name, or show all corporations.') }}</p>
        </div>

        <ul class="cards">
            @foreach ($corporations as $corporation)
                <li style="display: flex" data-filter-item data-filter-text="{{ $corporation->name }} {{ $corporation->acronym }} {{ $corporation->description }}"
                    data-filter-tags="{{ $corporation->on_duty > 0 ? 'open' : '' }}">
                    <a class="prp-article" href="{{ route('corporations.show', $corporation->id) }}" style="flex: 1">
                        <div class="prp-article-body" style="padding: 20px; gap: 14px; height: 100%">
                            <div class="row row-start">
                                <x-prp.corp-badge :corporation="$corporation" size="lg" />
                                <div class="grow"></div>
                                @if ($myCorporationId === $corporation->id)
                                    <span class="prp-tag prp-tag-brand">{{ __('You work here') }}</span>
                                @elseif ($corporation->on_duty > 0)
                                    <span class="prp-tag prp-tag-accent">{{ __('Open now') }}</span>
                                @endif
                            </div>
                            <div class="stack g1">
                                <h2 class="prp-article-title">{{ $corporation->name }}</h2>
                                <p class="t-sm muted">{{ $corporation->description }}</p>
                            </div>
                            <div class="prp-article-meta wrap" style="margin-top: auto; gap: 6px 14px">
                                <span class="row g1" style="gap: 6px">
                                    <x-prp.icon name="users" class="ico-sm" />
                                    <span><span class="t-num">{{ $corporation->employees }}</span> {{ trans_choice('employee|employees', $corporation->employees) }}</span>
                                </span>
                                <span class="row" style="gap: 6px">
                                    <span class="prp-dot {{ $corporation->on_duty > 0 ? 'prp-dot-online' : '' }}"></span>
                                    <span><span class="t-num">{{ $corporation->on_duty }}</span> {{ __('on shift') }}</span>
                                </span>
                            </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
