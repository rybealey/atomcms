{{--
    Settings pages 1 to 4 of the wizard. The settings table is split into four
    chunks by the controller; this renders one chunk with the buttons to go
    on, back or start over. Expects $step (1-4) and $settings.
--}}
@include('installation.partials.head', [
    'step' => $step + 1,
    'title' => __('Hotel settings'),
    'lead' => __('Part :part of 4. Every value can be changed later from the housekeeping.', ['part' => $step]),
])

{{-- Back and restart post elsewhere, so they live outside the settings form and the footer buttons point at them. --}}
<form id="inst-restart" method="POST" action="{{ route('installation.restart') }}" hidden
    data-confirm="{{ __('Start the setup over? A new installation key is generated and you will need to read it from the database again.') }}">
    @csrf
</form>

@if ($step > 1)
    <form id="inst-prev" method="POST" action="{{ route('installation.previous-step') }}" hidden>
        @csrf
    </form>
@endif

<form class="prp-card" method="POST" action="{{ route('installation.save-step') }}" aria-labelledby="inst-form-title">
    @csrf

    <div class="sec-head">
        <h2 id="inst-form-title" class="t-h2">{{ __('Settings, part :part of 4', ['part' => $step]) }}</h2>
        <p class="t-sm muted">{{ __('Fields come filled in with their defaults. The note under each one says what it is for.') }}</p>
    </div>

    <div class="card-pad stack g5">
        @forelse ($settings as $setting)
            @include('installation.partials.setting-input')
        @empty
            <p class="t-body muted">{{ __('Nothing to set on this page.') }}</p>
        @endforelse
    </div>

    <div class="card-foot between" style="align-items: center">
        <div class="row g2 wrap">
            <button type="submit" form="inst-restart" class="prp-btn prp-btn-quiet prp-btn-danger">{{ __('Start over') }}</button>
            @if ($step > 1)
                <button type="submit" form="inst-prev" class="prp-btn prp-btn-ghost">{{ __('Previous step') }}</button>
            @endif
        </div>

        <button type="submit" class="prp-btn prp-btn-primary">
            {{ $step < 4 ? __('Continue to step :step', ['step' => $step + 1]) : __('Complete setup') }}
        </button>
    </div>
</form>
