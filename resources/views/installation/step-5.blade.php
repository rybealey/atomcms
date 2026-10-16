<x-installation-layout>
    @push('title', __('Setup'))

    @include('installation.partials.head', [
        'step' => 6,
        'title' => __('All set'),
        'lead' => __(':hotel is ready to open its doors.', ['hotel' => setting('hotel_name')]),
    ])

    <form id="inst-restart" method="POST" action="{{ route('installation.restart') }}" hidden
        data-confirm="{{ __('Start the setup over? A new installation key is generated and you will need to read it from the database again.') }}">
        @csrf
    </form>

    <form id="inst-prev" method="POST" action="{{ route('installation.previous-step') }}" hidden>
        @csrf
    </form>

    <form class="prp-card" method="POST" action="{{ route('installation.complete') }}" aria-labelledby="inst-form-title">
        @csrf

        <div class="sec-head">
            <h2 id="inst-form-title" class="t-h2">{{ __('Setup complete') }}</h2>
            <p class="t-sm muted">{{ __('Congratulations, and welcome to the Atom CMS family.') }}</p>
        </div>

        <div class="card-pad stack g3">
            <p class="t-body">
                {{ __('From here you manage the hotel from the housekeeping, while your visitors get an easy way into the city.') }}
            </p>

            <p class="t-body">
                {{ __('Any setting you went through can be changed at any time: in the housekeeping, or straight in the "website_settings" table of your database.') }}
            </p>

            <p class="t-body">
                {!! __('The <a href=":documentation_link" target="_blank" rel="noopener" class="link">documentation</a> covers managing Atom CMS and the rest of your hotel, and keeps growing.', [
                    'documentation_link' => 'https://github.com/atom-retros/atomcms/wiki',
                ]) !!}
            </p>
        </div>

        <div class="card-foot between" style="align-items: center">
            <div class="row g2 wrap">
                <button type="submit" form="inst-restart" class="prp-btn prp-btn-quiet prp-btn-danger">{{ __('Start over') }}</button>
                <button type="submit" form="inst-prev" class="prp-btn prp-btn-ghost">{{ __('Previous step') }}</button>
            </div>
            <button type="submit" class="prp-btn prp-btn-primary">{{ __('Take me to :hotel', ['hotel' => setting('hotel_name')]) }}</button>
        </div>
    </form>
</x-installation-layout>
