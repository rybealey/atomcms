<x-app-layout active="settings">
    @push('title', __('Sessions'))

    <div class="pg-head">
        <div class="pg-head-text">
            <h1 class="t-h1">{{ __('Settings') }}</h1>
            <p class="t-body muted">{{ __("Manage your account, security and where you're logged in.") }}</p>
        </div>
    </div>

    <div class="set">
        <x-prp.settings-nav />

        <div class="set-body">
            @if ($failedThisWeek > 0)
                <div class="prp-alert prp-alert-warning">
                    <x-prp.icon name="warning" class="prp-alert-icon" />
                    <div>
                        <span class="prp-alert-title">{{ trans_choice(':count failed log in attempt this week|:count failed log in attempts this week', $failedThisWeek, ['count' => $failedThisWeek]) }}</span>
                        {!! __("If those weren't you, :password and turn on :twofactor.", [
                            'password' => '<a class="link link-inherit" href="'.e(route('settings.password.show')).'">'.e(__('change your password')).'</a>',
                            'twofactor' => '<a class="link link-inherit" href="'.e(route('settings.two-factor')).'">'.e(__('two-factor')).'</a>',
                        ]) !!}
                    </div>
                </div>
            @endif

            <section class="prp-card" aria-labelledby="ss-active">
                <div class="sec-head">
                    <h2 id="ss-active" class="t-h2">{{ __("Where you're logged in") }}</h2>
                    <p class="t-sm muted">
                        @if ($sessionsAvailable)
                            {{ trans_choice(':count active session|:count active sessions', $sessions->count(), ['count' => $sessions->count()]) }}. {{ __('Addresses are shortened for your privacy.') }}
                        @else
                            {{ __('This hotel keeps sessions in files, so they cannot be listed here. Changing your password is the surest way to end them all.') }}
                        @endif
                    </p>
                </div>

                @if ($sessions->isNotEmpty())
                    <ul class="divide list-reset" style="margin-top: 16px; border-top: 1px solid var(--line)">
                        @foreach ($sessions as $session)
                            <li class="list-row wrap" style="padding-top: 14px; padding-bottom: 14px">
                                <span class="prp-card-icon prp-card-icon-neutral">
                                    <x-prp.icon :name="$session->agent['is_desktop'] ? 'monitor' : 'phone'" />
                                </span>
                                <div class="stack grow" style="gap: 2px; flex: 1 1 240px">
                                    <span class="row g2">
                                        <span class="t-label-md">{{ $session->device() }}</span>
                                        @if ($session->isCurrentDevice)
                                            <span class="prp-tag prp-tag-brand">{{ __('This device') }}</span>
                                        @endif
                                    </span>
                                    <span class="t-sm muted"><span class="t-num">{{ $session->ipAddress }}</span> · {{ $session->isCurrentDevice ? __('Active now') : __('Last active :when', ['when' => $session->lastActive]) }}</span>
                                </div>
                                @unless ($session->isCurrentDevice)
                                    <form method="POST" action="{{ route('settings.sessions.destroy', $session->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="prp-btn prp-btn-ghost prp-btn-sm" aria-label="{{ __('Log out :device', ['device' => $session->device()]) }}">{{ __('Log out') }}</button>
                                    </form>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($sessionsAvailable && $sessions->count() > 1)
                    <div class="card-foot" style="justify-content: flex-start">
                        <form method="POST" action="{{ route('settings.sessions.destroy-others') }}" data-confirm="{{ __('Log out of every other browser and device?') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="prp-btn prp-btn-ghost prp-btn-danger">{{ __('Log out of all other sessions') }}</button>
                        </form>
                    </div>
                @endif
            </section>

            <section class="prp-card" aria-labelledby="ss-log" data-filter-root>
                <div class="row wrap between" style="padding: 20px 20px 12px; gap: 12px">
                    <div class="stack g1">
                        <h2 id="ss-log" class="t-h2">{{ __('Log in history') }}</h2>
                        <p class="t-sm muted">{{ __('The last 30 days. We keep this log for 90 days.') }}</p>
                    </div>
                    <div class="seg" role="group" aria-label="{{ __('Filter history') }}">
                        <button type="button" aria-pressed="true" data-filter="all">{{ __('All') }}</button>
                        <button type="button" aria-pressed="false" data-filter="fail">{{ __('Failed only') }}</button>
                    </div>
                </div>

                @if ($history->isEmpty())
                    <div class="prp-card-body" style="padding-top: 0"><p class="t-sm muted">{{ __('No log ins recorded yet. Your next one will show here.') }}</p></div>
                @else
                    <div class="tbl-box">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('When') }}</th>
                                    <th scope="col">{{ __('Result') }}</th>
                                    <th scope="col">{{ __('Method') }}</th>
                                    <th scope="col">{{ __('Device') }}</th>
                                    <th scope="col">{{ __('IP address') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($history as $entry)
                                    <tr data-filter-item data-filter-tags="{{ $entry->ok ? 'ok' : 'fail' }}">
                                        <td>{{ $entry->when->isToday() ? __('Today, :time', ['time' => $entry->when->format('g:i A')]) : ($entry->when->isYesterday() ? __('Yesterday, :time', ['time' => $entry->when->format('g:i A')]) : $entry->when->format('M j, g:i A')) }}</td>
                                        <td>
                                            <span class="row {{ $entry->ok ? 'success-ink' : 'danger-ink' }}" style="gap: 6px; font-weight: 500">
                                                <x-prp.icon :name="$entry->ok ? 'check' : 'x'" stroke="2" class="ico-sm" />
                                                {{ $entry->ok ? __('Success') : __('Failed') }}
                                            </span>
                                        </td>
                                        <td class="muted">
                                            {{ match ($entry->method) {
                                                'password-2fa' => __('Password and 2FA'),
                                                'wrong-password' => __('Wrong password'),
                                                'unknown-user' => __('Unknown username'),
                                                default => __('Password'),
                                            } }}
                                        </td>
                                        <td>{{ $entry->device }}</td>
                                        <td class="t-num">{{ $entry->ip }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="t-sm muted card-pad" data-filter-empty hidden>{{ __('No failed attempts in the last 30 days.') }}</p>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
