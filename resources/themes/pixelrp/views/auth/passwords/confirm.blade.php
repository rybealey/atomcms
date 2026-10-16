{{--
    Fortify's password confirmation checkpoint (/user/confirm-password). The
    `password.confirm` middleware sends people here before the two-factor
    settings, and Fortify returns them to where they were going afterwards.
--}}
<x-app-layout active="settings">
    @push('title', __('Confirm your password'))

    <div class="pg-head">
        <div class="pg-head-text">
            <h1 class="t-h1">{{ __('Settings') }}</h1>
            <p class="t-body muted">{{ __("Manage your account, security and where you're logged in.") }}</p>
        </div>
    </div>

    <div class="set">
        <x-prp.settings-nav />

        <div class="set-body">
            <x-prp.alert :flash="false" :handled="['password']" />

            <form class="prp-card" aria-labelledby="cp-title" method="POST" action="{{ url('/user/confirm-password') }}">
                @csrf

                <div class="sec-head">
                    <h2 id="cp-title" class="t-h2">{{ __('Confirm your password') }}</h2>
                    <p class="t-sm muted">{{ __("Before you change security settings, confirm it's really you.") }}</p>
                </div>

                <div class="card-pad stack g5">
                    <div class="prp-alert prp-alert-info">
                        <x-prp.icon name="info" class="prp-alert-icon" />
                        <div>{{ __("You'll only be asked once in a while. Your password is never shown to anyone else.") }}</div>
                    </div>

                    <x-prp.field name="password" id="cp-pass" type="password" :label="__('Current password')" autocomplete="current-password" :autofocus="true" />

                    <x-prp.captcha />
                </div>

                <div class="card-foot between" style="align-items: center">
                    <a class="link t-sm" href="{{ route('forgot.password.get') }}">{{ __('Forgot your password?') }}</a>
                    <button type="submit" class="prp-btn prp-btn-primary">{{ __('Confirm') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
