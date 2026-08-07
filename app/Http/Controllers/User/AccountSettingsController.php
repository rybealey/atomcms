<?php

namespace App\Http\Controllers\User;

use App\Contracts\Rcon;
use App\Emulator\Data\Feature;
use App\Emulator\Emulator;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountSettingsFormRequest;
use App\Services\User\SessionService;
use App\Support\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountSettingsController extends Controller
{
    public function edit(): View
    {
        $user = AuthenticatedUser::current();

        // The `settings` relation only exists on drivers that keep a
        // users_settings table (see UserObserver::createEmulatorSettings());
        // both eager-loading it and merely touching $user->settings lazily
        // run a query against that table, throwing a missing-table error on
        // drivers without it. Resolve the flag up front, behind the feature
        // check, so the blade never has to touch the relation itself.
        $allowNameChange = Emulator::supports(Feature::EmulatorUserSettings)
            && $user->load('settings:allow_name_change')->settings?->allow_name_change;

        return view('user.settings.account', [
            'user' => $user,
            'allowNameChange' => (bool) $allowNameChange,
        ]);
    }

    public function sessionLogs(Request $request, SessionService $sessionService): View
    {
        $sessions = $sessionService->fetchSessionLogs($request);

        return view('user.settings.session-logs', [
            'logs' => $sessions,
        ]);
    }

    public function update(AccountSettingsFormRequest $request, Rcon $rcon): RedirectResponse
    {
        $user = AuthenticatedUser::from($request);

        if (! $rcon->isConnected() && $user->online) {
            return back()->withErrors('You must be offline to change your account settings');
        }

        if ($user->mail !== $request->input('mail')) {
            $user->update(['mail' => $request->input('mail')]);
        }

        // The motto is nullable in validation; clearing it means an empty string.
        $motto = (string) $request->input('motto');

        if ($user->motto !== $motto) {
            $rcon->setMotto($user, $motto);
            $user->update(['motto' => $motto]);
        }

        return redirect()->route('settings.account.show')->with('success', __('Your account settings has been updated'));
    }

    public function twoFactor(): View
    {
        return view('user.settings.two-factor');
    }
}
