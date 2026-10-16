{{--
    The maintenance notice. Guests and players below the maintenance rank are
    held here; staff log in from the card below and are let through by
    MaintenanceMiddleware once their rank clears the bar.
--}}
@php
    $loginFailed = $errors->has('username') || $errors->has('password');
@endphp

<x-guest-layout wide aria-labelledby="mt-title">
    @push('title', __('Maintenance'))

    <div class="stack g2">
        <img class="px-img auth-logo" src="{{ asset('assets/images/pixelrp/logo-hero.gif') }}" width="201" height="55" alt="{{ setting('hotel_name') }}">
        <p class="t-over brand-ink">{{ __('Maintenance break') }}</p>
        <h1 id="mt-title" class="t-h1">{{ __('The city is closed for a moment') }}</h1>
        <div class="t-body muted">{!! \Stevebauman\Purify\Facades\Purify::clean(setting('maintenance_message')) !!}</div>
    </div>

    @if ($tasks->isNotEmpty())
        <section class="prp-card" aria-labelledby="mt-tasks">
            <div class="sec-head" style="padding-bottom: 16px">
                <h2 id="mt-tasks" class="t-h3">{{ __("What we're working on") }}</h2>
            </div>

            <ul class="list-reset divide">
                @foreach ($tasks as $task)
                    <li class="list-row row-start">
                        @if ($task->user !== null)
                            <x-prp.head :look="$task->user->look" :px="36" alt="" />
                        @else
                            <span class="prp-avatar" style="width: 36px; height: 36px" aria-hidden="true">?</span>
                        @endif

                        <span class="stack grow g1">
                            <span class="t-body" style="white-space: normal; overflow-wrap: anywhere">{{ $task->task }}</span>
                            @if ($task->user !== null)
                                <span class="t-sm muted">{{ __('By: :user', ['user' => $task->user->username]) }}</span>
                            @endif
                        </span>

                        @if ($task->completed)
                            <span class="prp-tag prp-tag-success">{{ __('Done') }}</span>
                        @else
                            <span class="prp-tag prp-tag-accent">{{ __('In progress') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($tasks->hasPages())
                <nav class="card-foot between" aria-label="{{ __('Maintenance tasks pages') }}">
                    @if ($tasks->onFirstPage())
                        <span></span>
                    @else
                        <a class="prp-btn prp-btn-quiet prp-btn-sm" href="{{ $tasks->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>
                    @endif

                    @if ($tasks->hasMorePages())
                        <a class="prp-btn prp-btn-quiet prp-btn-sm" href="{{ $tasks->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
                    @endif
                </nav>
            @endif
        </section>
    @endif

    @guest
        <details class="reveal stack g4" @if ($loginFailed || session()->has('wrong-auth')) open @endif>
            <summary class="prp-btn prp-btn-ghost prp-btn-block">{{ __('Staff login') }}</summary>

            @if ($loginFailed)
                <div class="prp-alert prp-alert-danger" role="alert">
                    <x-prp.icon name="alert-circle" class="prp-alert-icon" />
                    <div>
                        <span class="prp-alert-title">{{ __("Couldn't log you in") }}</span>
                        {{ $errors->first('username') ?: $errors->first('password') }}
                    </div>
                </div>
            @endif

            <x-prp.alert :handled="['username', 'password']" />

            <form class="stack g4" method="POST" action="{{ route('login') }}" aria-label="{{ __('Staff login') }}">
                @csrf

                <div class="prp-field">
                    <label class="prp-field-label" for="mt-user">{{ __('Username') }}</label>
                    <input class="prp-input" id="mt-user" name="username" value="{{ old('username') }}" autocomplete="username"
                        autocapitalize="none" spellcheck="false" required
                        @if ($loginFailed) aria-invalid="true" @endif>
                </div>

                <div class="prp-field">
                    <label class="prp-field-label" for="mt-pass">{{ __('Password') }}</label>
                    <div class="pw">
                        <input class="prp-input" id="mt-pass" name="password" type="password" autocomplete="current-password" required
                            @if ($loginFailed) aria-invalid="true" @endif>
                        <button type="button" class="prp-btn prp-btn-quiet prp-btn-sm" aria-controls="mt-pass" aria-pressed="false"
                            data-pw-toggle data-show-label="{{ __('Show') }}" data-hide-label="{{ __('Hide') }}">{{ __('Show') }}</button>
                    </div>
                </div>

                <div class="row between wrap">
                    <x-prp.check name="remember" :checked="true">{{ __('Keep me logged in') }}</x-prp.check>
                    <a class="link t-sm" href="{{ route('forgot.password.get') }}">{{ __('Forgot password?') }}</a>
                </div>

                <x-prp.captcha />

                <button type="submit" class="prp-btn prp-btn-primary prp-btn-lg prp-btn-block">{{ __('Log in') }}</button>
            </form>
        </details>
    @else
        <p class="t-sm muted ta-center">
            {{ __('You are logged in as :name. Staff above the maintenance rank can enter the city now.', ['name' => auth()->user()->username]) }}
        </p>
    @endguest
</x-guest-layout>
