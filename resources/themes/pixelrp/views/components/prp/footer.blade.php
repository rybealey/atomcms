<footer class="pg-foot">
    <div class="pg-foot-in">
        <span>{{ __(':hotel is a not-for-profit educational project.', ['hotel' => setting('hotel_name')]) }}</span>
        <nav aria-label="{{ __('Footer') }}">
            <a href="{{ route('help-center.rules.index') }}">{{ __('Terms') }}</a>
            <a href="{{ route('help-center.rules.index') }}#privacy">{{ __('Privacy') }}</a>
            <a href="{{ setting('discord_invitation_link') }}" target="_blank" rel="noopener">Discord</a>
        </nav>
    </div>
</footer>
