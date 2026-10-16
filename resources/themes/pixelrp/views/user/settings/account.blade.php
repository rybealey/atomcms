<x-app-layout active="settings">
    @push('title', __('Account settings'))

    <div class="pg-head">
        <div class="pg-head-text">
            <h1 class="t-h1">{{ __('Settings') }}</h1>
            <p class="t-body muted">{{ __("Manage your account, security and where you're logged in.") }}</p>
        </div>
    </div>

    <div class="set">
        <x-prp.settings-nav />

        <div class="set-body">
            <x-prp.alert :flash="false" :handled="['username', 'mail', 'motto', 'current_password']" />

            <form class="prp-card" aria-labelledby="sa-profile" method="POST" action="{{ route('settings.account.update') }}">
                @method('PUT')
                @csrf

                <div class="sec-head">
                    <h2 id="sa-profile" class="t-h2">{{ __('Profile') }}</h2>
                    <p class="t-sm muted">{{ __('How other citizens see you on the website.') }}</p>
                </div>

                <div class="card-pad form-grid">
                    @if ($allowNameChange)
                        <x-prp.field name="username" id="sa-user" :label="__('Username')" :value="$user->username" autocomplete="username"
                            :hint="__('Your username is what you and others see in the city.')" />
                    @else
                        <x-prp.field name="username_display" id="sa-user" :label="__('Username')" :value="$user->username" :readonly="true" :required="false"
                            :hint="__('To change your name, ask staff in game.')" />
                    @endif

                    <x-prp.field name="mail" id="sa-mail" type="email" :label="__('Email')" :value="$user->mail" autocomplete="email"
                        data-reveal-on-change="sa-current-password" :hint="__('Changing your address asks for your password first.')">
                        <x-slot:labelEnd>
                            @if ((string) $user->mail_verified === '1')
                                <span class="prp-tag prp-tag-success" style="height: 20px">{{ __('Verified') }}</span>
                            @endif
                        </x-slot:labelEnd>
                    </x-prp.field>

                    <div class="full">
                        <x-prp.field name="motto" id="sa-motto" :label="__('Motto')" :value="$user->motto" :required="false" maxlength="38" aria-describedby="sa-motto-count">
                            <x-slot:labelEnd>
                                <span class="t-num muted" id="sa-motto-count" data-count-for="sa-motto">{{ mb_strlen((string) $user->motto) }} / 38</span>
                            </x-slot:labelEnd>
                        </x-prp.field>
                    </div>

                    <div class="full" id="sa-current-password" @if (! $errors->has('current_password')) hidden @else data-force-open="1" @endif>
                        <x-prp.field name="current_password" type="password" :label="__('Current password')" autocomplete="current-password" :required="false"
                            :hint="__('Required to change your email address.')" />
                    </div>

                    <div class="full"><x-prp.captcha /></div>
                </div>

                <div class="card-foot">
                    <button type="submit" class="prp-btn prp-btn-primary">{{ __('Save profile') }}</button>
                </div>
            </form>

            <section class="prp-card" aria-labelledby="sa-look">
                <div class="sec-head">
                    <h2 id="sa-look" class="t-h2">{{ __('Appearance') }}</h2>
                    <p class="t-sm muted">{{ __('Choose how the website looks. The game client keeps its own look.') }}</p>
                </div>
                <fieldset class="card-pad stack g3" style="margin: 0; border: 0">
                    <legend class="sr-only">{{ __('Theme') }}</legend>
                    @foreach ([
                        'system' => [__('Match my device'), __('Light or dark, following your system setting.')],
                        'light' => [__('Light'), __('Warm paper tones.')],
                        'dark' => [__('Dark'), __('Night plum, easier on the eyes after hours.')],
                    ] as $value => [$label, $hint])
                        <label class="radio-row">
                            <input type="radio" name="theme" value="{{ $value }}" @checked($value === 'system')>
                            <span class="stack" style="gap: 2px">
                                <span class="t-label-md">{{ $label }}</span>
                                <span class="t-sm muted">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>
            </section>
        </div>
    </div>
</x-app-layout>
