<x-guest-layout aria-labelledby="ban-title">
    @push('title', __('Banned'))

    <div class="stack g2">
        <img class="px-img auth-logo" src="{{ asset('assets/images/pixelrp/logo-hero.gif') }}" width="201" height="55" alt="{{ setting('hotel_name') }}">
        <p class="t-over danger-ink">{{ __('Account suspended') }}</p>
        <h1 id="ban-title" class="t-h1">{{ __("You can't enter the city right now") }}</h1>
        <p class="t-body muted">{{ __('This account or connection has been banned from :hotel.', ['hotel' => setting('hotel_name')]) }}</p>
    </div>

    @if ($ban !== null)
        <div class="kv">
            <div class="kv-cell"><span class="t-sm muted">{{ __('Ban type') }}</span><span class="t-label-lg">{{ $ban->type }}</span></div>
            <div class="kv-cell"><span class="t-sm muted">{{ __('Expires') }}</span><span class="t-num-md">{{ date('M j, Y', $ban->ban_expire) }}</span></div>
            <div class="kv-cell full" style="grid-column: 1 / -1"><span class="t-sm muted">{{ __('Reason') }}</span><span class="t-body">{{ $ban->ban_reason }}</span></div>
        </div>
    @endif

    <p class="t-sm muted">{{ __('If you believe this is a mistake, reach out to a member of staff on Discord.') }}</p>

    <a class="prp-btn prp-btn-primary prp-btn-lg prp-btn-block" href="{{ setting('discord_invitation_link') }}" target="_blank" rel="noopener">{{ __('Get help on Discord') }}</a>
</x-guest-layout>
