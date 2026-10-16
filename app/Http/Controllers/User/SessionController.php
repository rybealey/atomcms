<?php

namespace App\Http\Controllers\User;

use App\Data\LoginHistoryEntry;
use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\User\WebsiteLoginLog;
use App\Services\User\SessionService;
use App\Support\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where you're logged in, and the log in history behind it.
 *
 * Active sessions come from the sessions table, so they are only listed when
 * SESSION_DRIVER=database; the history is the CMS's own website_login_logs,
 * written by RecordLoginAttempt on every attempt whatever the driver.
 */
class SessionController extends Controller
{
    public function index(Request $request, SessionService $sessionService): View
    {
        $user = AuthenticatedUser::from($request);

        $history = WebsiteLoginLog::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('user.settings.session-logs', [
            'sessions' => $sessionService->fetchSessionLogs($request),
            'sessionsAvailable' => config('session.driver') === 'database',
            'history' => $history->map(fn (WebsiteLoginLog $log): LoginHistoryEntry => new LoginHistoryEntry(
                when: $log->created_at,
                ok: $log->succeeded,
                method: $log->method,
                device: $sessionService->describeAgent((string) $log->user_agent),
                ip: $sessionService->maskIp((string) $log->ip_address),
            )),
            'failedThisWeek' => WebsiteLoginLog::query()
                ->where('user_id', $user->id)
                ->where('succeeded', false)
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
        ]);
    }

    /** Log out one other browser or device. */
    public function destroy(Request $request, string $session): RedirectResponse
    {
        $user = AuthenticatedUser::from($request);

        abort_if($session === $request->session()->getId(), 422);

        Session::query()->where('user_id', $user->id)->whereKey($session)->delete();

        return back()->with('success', __('That device has been logged out.'));
    }

    /** Log out every browser and device except this one. */
    public function destroyOthers(Request $request): RedirectResponse
    {
        $user = AuthenticatedUser::from($request);

        Session::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', __('Every other session has been logged out.'));
    }
}
