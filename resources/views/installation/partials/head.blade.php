{{--
    The page head every wizard screen shares: where you are in the six
    screens (key, four settings pages, done), the title and a progress bar.
    Expects $step (1-6), $title and optionally $lead.
--}}
@php
    $total = 6;
    $percent = (int) round(($step - 1) / ($total - 1) * 100);
@endphp

<div class="pg-head">
    <div class="pg-head-text">
        <p class="t-over brand-ink">{{ __('Setup') }} · {{ __('Step :step of :total', ['step' => $step, 'total' => $total]) }}</p>
        <h1 class="t-h1">{{ $title }}</h1>
        @if (! empty($lead))
            <p class="t-body muted">{{ $lead }}</p>
        @endif
    </div>

    <div class="stack g1" style="flex: 1 1 200px; max-width: 260px">
        <span class="t-sm muted" id="inst-progress-label"><span class="t-num">{{ $percent }}%</span> {{ __('complete') }}</span>
        <div class="prog" role="progressbar" aria-valuemin="1" aria-valuemax="{{ $total }}" aria-valuenow="{{ $step }}" aria-labelledby="inst-progress-label">
            <span style="width: {{ $percent }}%"></span>
        </div>
    </div>
</div>
