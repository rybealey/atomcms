<x-app-layout>
    @push('title', __('Terms and city rules'))

    <div class="pg-head">
        <div class="pg-head-text">
            <h1 class="t-h1">{{ __('Terms and city rules') }}</h1>
            <p class="t-body muted">{{ __('Rules and regulations can change without notice. By playing in :hotel you agree to them; breaking them leads to sanctions on your account. Ask a member of staff if anything is unclear.', ['hotel' => setting('hotel_name')]) }}</p>
        </div>
    </div>

    <div class="stack g6">
        @forelse ($categories as $category)
            <section class="prp-card" aria-labelledby="rule-cat-{{ $category->id }}">
                <div class="prp-card-head">
                    <div class="prp-card-titles">
                        <h2 id="rule-cat-{{ $category->id }}" class="prp-card-title">{{ $category->name }}</h2>
                        @if ($category->description)
                            <p class="prp-card-sub">{{ $category->description }}</p>
                        @endif
                    </div>
                </div>
                <ol class="prp-card-body stack g3" style="margin: 0; padding-left: 40px">
                    @foreach ($category->rules as $rule)
                        <li class="t-body"><strong style="font-weight: 500">{{ $rule->paragraph }}.</strong> {{ $rule->rule }}</li>
                    @endforeach
                </ol>
            </section>
        @empty
            <div class="prp-card card-pad stack g2 empty">
                <h2 class="t-h3">{{ __('No rules have been published yet') }}</h2>
                <p class="t-sm muted">{{ __('Check back soon.') }}</p>
            </div>
        @endforelse
    </div>
</x-app-layout>
