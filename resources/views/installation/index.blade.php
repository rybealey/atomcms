<x-installation-layout>
    @push('title', __('Setup'))

    @include('installation.partials.head', [
        'step' => 1,
        'title' => __('Welcome to Atom CMS'),
        'lead' => __('A few screens of settings and your hotel is on the web. Nothing here is final: every value can be changed later.'),
    ])

    <form class="prp-card" method="POST" action="{{ route('installation.start-installation') }}" aria-labelledby="inst-form-title">
        @csrf

        <div class="sec-head">
            <h2 id="inst-form-title" class="t-h2">{{ __('Before we start') }}</h2>
            <p class="t-sm muted">{{ __('Thank you for choosing Atom CMS for your hotel.') }}</p>
        </div>

        <div class="card-pad stack g5">
            <div class="stack g3 t-body">
                <p class="t-body">
                    {{ __('Atom CMS is built with the community in mind: an easy way to run your hotel without a degree in programming, shaped by the suggestions of the people who use it.') }}
                </p>

                <p class="t-body">
                    {!! __('The <a href=":documentation_link" target="_blank" rel="noopener" class="link">documentation</a> walks through the first setup and what comes after, and <a href=":discord_link" target="_blank" rel="noopener" class="link">the Discord</a> is where to ask when something is unclear.', [
                        'documentation_link' => 'https://github.com/atom-retros/atomcms/wiki',
                        'discord_link' => 'https://discord.gg/pP6HyZedAj',
                    ]) !!}
                </p>
            </div>

            <div class="prp-alert prp-alert-info">
                <svg class="prp-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                <div>
                    <span class="prp-alert-title">{{ __('The installation key') }}</span>
                    {{ __('So nobody else can run this setup, it asks for the key in your database: the "website_installation" table, column "installation_key".') }}
                </div>
            </div>

            <div class="prp-field">
                <label class="prp-field-label" for="installation_key">{{ __('Installation key') }}</label>
                <input class="prp-input mono-input" id="installation_key" type="text" name="installation_key" value="{{ old('installation_key') }}"
                    placeholder="{{ __('Enter your installation key') }}" autocomplete="off" autocapitalize="none" spellcheck="false" required autofocus
                    @error('installation_key') aria-invalid="true" aria-describedby="installation_key-error" @enderror>
                @error('installation_key')
                    <span class="prp-field-error" id="installation_key-error">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="card-foot">
            <button type="submit" class="prp-btn prp-btn-primary">{{ __('Start the setup') }}</button>
        </div>
    </form>
</x-installation-layout>
