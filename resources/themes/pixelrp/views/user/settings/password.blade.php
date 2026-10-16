<x-app-layout active="settings">
    @push('title', __('Password settings'))

    <div class="pg-head">
        <div class="pg-head-text">
            <h1 class="t-h1">{{ __('Settings') }}</h1>
            <p class="t-body muted">{{ __("Manage your account, security and where you're logged in.") }}</p>
        </div>
    </div>

    <div class="set">
        <x-prp.settings-nav />

        <div class="set-body">
            <x-prp.alert :flash="false" :handled="['current_password', 'password', 'password_confirmation']" />

            <form class="prp-card" aria-labelledby="sp-title" method="POST" action="{{ route('settings.password.update') }}">
                @method('PUT')
                @csrf

                <div class="sec-head">
                    <h2 id="sp-title" class="t-h2">{{ __('Change password') }}</h2>
                    <p class="t-sm muted">{{ __('Pick one you do not use anywhere else.') }}</p>
                </div>

                <div class="card-pad stack g5">
                    <div class="prp-alert prp-alert-info">
                        <x-prp.icon name="info" class="prp-alert-icon" />
                        <div>{{ __('Changing your password does not log out your other devices. You can do that under Sessions.') }}</div>
                    </div>

                    <x-prp.field name="current_password" id="sp-cur" type="password" :label="__('Current password')" autocomplete="current-password" :autofocus="true" />

                    <div class="prp-field">
                        <label class="prp-field-label" for="sp-new">{{ __('New password') }}</label>
                        <input class="prp-input" id="sp-new" name="password" type="password" autocomplete="new-password" required
                            aria-describedby="sp-strength sp-rules" data-pw-strength @error('password') aria-invalid="true" @enderror>
                        @error('password')
                            <span class="prp-field-error">{{ $message }}</span>
                        @enderror
                        <div class="row" style="gap: 10px; margin-top: 4px">
                            <div class="strength-bars" data-strength-bars data-score="0" aria-hidden="true"
                                data-words='@json([__('Too short'), __('Weak'), __('Fair'), __('Good'), __('Strong')])'>
                                <span></span><span></span><span></span><span></span>
                            </div>
                            <span class="t-sm" id="sp-strength" style="min-width: 110px; text-align: right">
                                {{ __('Strength') }}: <strong style="font-weight: 500" data-strength-label>{{ __('Too short') }}</strong>
                            </span>
                        </div>
                        <ul class="stack g1 list-reset" id="sp-rules" style="margin-top: 4px">
                            @foreach ([
                                'length' => __('At least 12 characters'),
                                'upper' => __('One capital letter'),
                                'lower' => __('One lowercase letter'),
                                'number' => __('One number'),
                                'symbol' => __('One special character'),
                            ] as $rule => $label)
                                <li class="row t-sm rule-no" style="gap: 8px" data-rule="{{ $rule }}" data-done-text="{{ __(', done') }}" data-todo-text="{{ __(', not yet') }}">
                                    <x-prp.icon name="check" stroke="2" class="ico-sm rule-ico-ok" />
                                    <svg class="ico-sm rule-ico-no" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle></svg>
                                    <span>{{ $label }}<span class="sr-only" data-rule-status>{{ __(', not yet') }}</span></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="prp-field">
                        <label class="prp-field-label" for="sp-new2">{{ __('Confirm new password') }}</label>
                        <input class="prp-input" id="sp-new2" name="password_confirmation" type="password" autocomplete="new-password" required
                            aria-describedby="sp-match" data-pw-confirm>
                        <span class="prp-field-error" id="sp-match" data-pw-mismatch hidden>{{ __("These passwords don't match yet.") }}</span>
                    </div>

                    <x-prp.captcha />
                </div>

                <div class="card-foot between" style="align-items: center">
                    <a class="link t-sm" href="{{ route('forgot.password.get') }}">{{ __('Forgot your current password?') }}</a>
                    <button type="submit" class="prp-btn prp-btn-primary">{{ __('Update password') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
