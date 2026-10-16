<?php

namespace App\Services\User;

use App\Data\SessionLogData;
use App\Models\Session;
use App\Support\AuthenticatedUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Jenssegers\Agent\Agent;

class SessionService
{
    /**
     * @return Collection<int, SessionLogData>
     */
    public function fetchSessionLogs(Request $request): Collection
    {
        return collect(
            AuthenticatedUser::from($request)->sessions()->orderByDesc('last_activity')->get(),
        )->map(function ($session) use ($request) {
            $agent = $this->createAgent($session);

            return new SessionLogData(
                agent: [
                    'is_desktop' => $agent->isDesktop(),
                    'platform' => $agent->platform(),
                    'browser' => $agent->browser(),
                ],
                ipAddress: $this->maskIp((string) $session->ip_address),
                isCurrentDevice: $session->id === $request->session()->getId(),
                lastActive: Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                id: (string) $session->id,
                lastActiveAt: Carbon::createFromTimestamp($session->last_activity),
            );
        });
    }

    /** "Safari on macOS", from a user agent string. */
    public function describeAgent(string $userAgent): string
    {
        if ($userAgent === '') {
            return __('Unknown device');
        }

        $agent = tap(new Agent, fn (Agent $agent) => $agent->setUserAgent($userAgent));
        $browser = $agent->browser() ?: __('Browser');
        $platform = $agent->platform() ?: __('unknown device');

        return __(':browser on :platform', ['browser' => $browser, 'platform' => $platform]);
    }

    /**
     * An IP address with its last block hidden: enough to recognise your own
     * connection, not enough to hand someone else's address around.
     */
    public function maskIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);

            return count($parts) > 2 ? implode(':', array_slice($parts, 0, 3)).':…' : $ip;
        }

        $parts = explode('.', $ip);

        return count($parts) === 4 ? implode('.', array_slice($parts, 0, 3)).'.xx' : $ip;
    }

    protected function createAgent(Session $session): Agent
    {
        return tap(new Agent, function ($agent) use ($session) {
            $agent->setUserAgent($session->user_agent);
        });
    }
}
