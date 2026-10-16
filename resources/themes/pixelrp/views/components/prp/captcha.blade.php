{{-- Both captchas are opt-in per install; neither renders unless enabled. --}}
@if (setting('google_recaptcha_enabled'))
    <div class="g-recaptcha" data-sitekey="{{ config('habbo.site.recaptcha_site_key') }}"></div>
@endif

@if (setting('cloudflare_turnstile_enabled'))
    <x-turnstile />
@endif
