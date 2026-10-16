@php
    $user = auth()->user();
    $enabled = $user->hasEnabledTwoFactorAuthentication();
    $pending = ! $enabled && $user->two_factor_secret !== null;
    $setupKey = $pending && method_exists($user, 'twoFactorSecretKey') ? (string) $user->twoFactorSecretKey() : '';
    $confirmedAt = $enabled && $user->two_factor_confirmed_at !== null ? $user->two_factor_confirmed_at : null;
    $codes = $enabled ? $user->recoveryCodes() : [];
@endphp

<x-app-layout active="settings">
    @push('title', __('Two-factor authentication'))

    <div class="pg-head">
        <div class="pg-head-text">
            <h1 class="t-h1">{{ __('Settings') }}</h1>
            <p class="t-body muted">{{ __("Manage your account, security and where you're logged in.") }}</p>
        </div>
    </div>

    <div class="set">
        <x-prp.settings-nav />

        <div class="set-body">
            <x-prp.alert :flash="false" :handled="['current_password', 'code']" />
            @if ($errors->confirmTwoFactorAuthentication->isNotEmpty())
                <div class="prp-alert prp-alert-danger" role="alert">
                    <x-prp.icon name="alert-circle" class="prp-alert-icon" />
                    <div>{{ $errors->confirmTwoFactorAuthentication->first() }}</div>
                </div>
            @endif

            <section class="prp-card" aria-labelledby="tf-title">
                <div class="row wrap row-start" style="padding: 20px; gap: 16px">
                    <span class="prp-card-icon" style="width: 44px; height: 44px"><x-prp.icon name="shield-check" class="ico-lg" /></span>
                    <div class="stack g1 grow" style="flex: 1 1 300px">
                        <div class="row" style="gap: 10px">
                            <h2 id="tf-title" class="t-h2">{{ __('Two-factor authentication') }}</h2>
                            @if ($enabled)
                                <span class="prp-tag prp-tag-success">{{ __('On') }}</span>
                            @else
                                <span class="prp-tag prp-tag-neutral">{{ __('Off') }}</span>
                            @endif
                        </div>
                        @if ($enabled)
                            <p class="t-sm muted">
                                {{ $confirmedAt !== null ? __('Turned on :date with an authenticator app.', ['date' => $confirmedAt->format('F j, Y')]) : __('Turned on with an authenticator app.') }}
                                {{ __("You'll be asked for a code each time you log in on a new device.") }}
                            </p>
                        @else
                            <p class="t-sm muted">{{ __("Add a 6-digit code from an authenticator app to your log in, so a stolen password isn't enough to get into your account.") }}</p>
                        @endif
                    </div>

                    @if (! $enabled && ! $pending)
                        <form class="row wrap g2" method="POST" action="{{ route('user.two-factor.enable') }}" style="flex: 1 1 320px; justify-content: flex-end">
                            @csrf
                            <label class="sr-only" for="tf-enable-pw">{{ __('Current password') }}</label>
                            <input class="prp-input" id="tf-enable-pw" name="current_password" type="password" autocomplete="current-password" required
                                placeholder="{{ __('Your password') }}" style="width: 200px; max-width: 100%" @error('current_password') aria-invalid="true" @enderror>
                            <button type="submit" class="prp-btn prp-btn-primary">{{ __('Set up two-factor') }}</button>
                            @error('current_password')
                                <span class="prp-field-error" style="flex-basis: 100%; text-align: right">{{ $message }}</span>
                            @enderror
                        </form>
                    @endif
                </div>
            </section>

            @if ($pending)
                <section class="prp-card" aria-labelledby="tf-setup">
                    <div class="sec-head">
                        <h2 id="tf-setup" class="t-h3">{{ __('Set up with an authenticator app') }}</h2>
                        <p class="t-sm muted">{{ __('Works with 1Password, Google Authenticator, Authy and any app that supports TOTP.') }}</p>
                    </div>
                    <div class="card-pad stack g6">
                        <div class="tf-step">
                            <span class="tf-num" aria-hidden="true">1</span>
                            <div class="stack g3 grow">
                                <h3 class="t-label-lg" style="margin: 0">{{ __('Scan this code with your app') }}</h3>
                                <div class="row wrap row-start" style="gap: 20px">
                                    <div class="tf-qr" role="img" aria-label="{{ __('QR code for setting up two-factor') }}">
                                        {!! $user->twoFactorQrCodeSvg() !!}
                                    </div>
                                    @if ($setupKey !== '')
                                        <div class="stack g2" style="flex: 1 1 220px">
                                            <span class="t-sm muted" id="tf-key-label">{{ __("Can't scan it? Enter this setup key instead.") }}</span>
                                            <div class="row wrap g2">
                                                <code class="tf-key" id="tf-key" aria-labelledby="tf-key-label" data-copy-value="{{ $setupKey }}">{{ trim(chunk_split($setupKey, 4, ' ')) }}</code>
                                                <button type="button" class="prp-btn prp-btn-ghost prp-btn-sm" data-copy="#tf-key" data-copied-label="{{ __('Copied') }}">{{ __('Copy key') }}</button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="tf-step">
                            <span class="tf-num" aria-hidden="true">2</span>
                            <form class="stack g3 grow" method="POST" action="{{ route('two-factor.verify') }}">
                                @csrf
                                <label class="t-label-lg" for="tf-code">{{ __('Enter the 6-digit code it shows') }}</label>
                                <div class="row wrap g3">
                                    <input class="prp-input code-input" id="tf-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                                        placeholder="000000" required style="max-width: 220px" @if ($errors->confirmTwoFactorAuthentication->has('code')) aria-invalid="true" @endif>
                                    <button type="submit" class="prp-btn prp-btn-primary prp-btn-lg">{{ __('Turn on two-factor') }}</button>
                                </div>
                                <x-prp.captcha />
                            </form>
                        </div>
                    </div>
                    <div class="card-foot" style="justify-content: flex-start">
                        <details class="reveal stack g3" style="width: 100%">
                            <summary class="prp-btn prp-btn-quiet">{{ __('Cancel setup') }}</summary>
                            <form class="row wrap g2" method="POST" action="{{ route('user.two-factor.disable') }}">
                                @csrf
                                @method('DELETE')
                                <label class="sr-only" for="tf-cancel-pw">{{ __('Current password') }}</label>
                                <input class="prp-input" id="tf-cancel-pw" name="current_password" type="password" autocomplete="current-password" required
                                    placeholder="{{ __('Your password') }}" style="width: 200px; max-width: 100%">
                                <button type="submit" class="prp-btn prp-btn-ghost">{{ __('Cancel setup') }}</button>
                            </form>
                        </details>
                    </div>
                </section>
            @endif

            @if ($enabled)
                <section class="prp-card" aria-labelledby="tf-rec">
                    <div class="sec-head">
                        <h2 id="tf-rec" class="t-h3">{{ __('Recovery codes') }}</h2>
                        <p class="t-sm muted">{{ __('If you lose your phone, each code logs you in once. Keep them somewhere safe.') }} {{ trans_choice(':count of 8 left|:count of 8 left', count($codes), ['count' => count($codes)]) }}</p>
                    </div>
                    <div class="card-pad stack g4">
                        <ul class="tf-codes" id="tf-code-list">
                            @foreach ($codes as $code)
                                <li data-recovery-code>{{ $code }}</li>
                            @endforeach
                        </ul>
                        <div class="row wrap g2">
                            <button type="button" class="prp-btn prp-btn-ghost prp-btn-sm" data-download-list="[data-recovery-code]" data-filename="{{ \Illuminate\Support\Str::slug(setting('hotel_name')) }}-recovery-codes.txt">
                                <x-prp.icon name="download" class="ico-sm" />{{ __('Download') }}
                            </button>
                            <button type="button" class="prp-btn prp-btn-ghost prp-btn-sm" data-copy-list="[data-recovery-code]" data-copied-label="{{ __('Copied') }}">
                                <x-prp.icon name="copy" class="ico-sm" />{{ __('Copy all') }}
                            </button>
                            <form method="POST" action="{{ route('user.two-factor.recovery-codes') }}" data-confirm="{{ __('Make new recovery codes? The current ones will stop working.') }}">
                                @csrf
                                <button type="submit" class="prp-btn prp-btn-quiet prp-btn-sm">{{ __('Make new codes') }}</button>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="prp-card" aria-labelledby="tf-off">
                    <div class="row wrap between" style="padding: 20px; gap: 16px">
                        <div class="stack g1" style="flex: 1 1 300px">
                            <h2 id="tf-off" class="t-h3">{{ __('Turn off two-factor') }}</h2>
                            <p class="t-sm muted">{{ __('Your account will only be protected by your password.') }}</p>
                        </div>
                        <form class="row wrap g2" method="POST" action="{{ route('user.two-factor.disable') }}" data-confirm="{{ __('Turn off two-factor authentication?') }}">
                            @csrf
                            @method('DELETE')
                            <label class="sr-only" for="tf-off-pw">{{ __('Current password') }}</label>
                            <input class="prp-input" id="tf-off-pw" name="current_password" type="password" autocomplete="current-password" required
                                placeholder="{{ __('Your password') }}" style="width: 200px; max-width: 100%" @error('current_password') aria-invalid="true" @enderror>
                            <button type="submit" class="prp-btn prp-btn-ghost prp-btn-danger">{{ __('Turn off') }}</button>
                        </form>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
