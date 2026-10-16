<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class TwoFactorAuthenticationController extends Controller
{
    public function index(): View
    {
        return view('user.settings.two-factor');
    }

    public function store(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $enable($request->user());

        return redirect()->route('settings.two-factor')->with('success', __('Two-factor authentication has been enabled. Please scan the QR code to continue.'));
    }

    public function verify(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $validated = $request->validateWithBag('confirmTwoFactorAuthentication', [
            'code' => ['required', 'string', 'size:6'],
        ]);

        $confirm($request->user(), $validated['code']);

        return redirect()->route('settings.two-factor')->with('success', __('Two-factor authentication has been confirmed.'));
    }

    /**
     * A fresh set of recovery codes; the old ones stop working at once.
     */
    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $user = AuthenticatedUser::from($request);

        abort_unless($user->hasEnabledTwoFactorAuthentication(), 403);

        $generate($user);

        return redirect()->route('settings.two-factor')->with('success', __('New recovery codes have been made. The old ones no longer work.'));
    }

    public function destroy(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $disable($request->user());

        return redirect()->route('settings.two-factor')->with('success', __('Two-factor authentication has been disabled.'));
    }
}
