<?php

namespace App\Listeners;

use App\Models\User;
use App\Models\User\WebsiteLoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Throwable;

/**
 * pixelrp: writes the log in history shown under Settings > Sessions.
 *
 * Both outcomes are recorded. A successful login that came through the
 * two-factor challenge is marked as such; a failure names the reason the
 * guard knew (a wrong password, or a username nobody has).
 */
class RecordLoginAttempt
{
    public function __construct(private readonly Request $request) {}

    public function handle(Failed|Login $event): void
    {
        // Session restores and other guards (Filament's panel, API tokens) are
        // not website log ins.
        if ($event->guard !== config('fortify.guard', 'web')) {
            return;
        }

        $user = $event->user instanceof User ? $event->user : null;
        $credentials = $event instanceof Failed ? $event->credentials : [];

        try {
            WebsiteLoginLog::query()->create([
                'user_id' => $user?->id,
                'username' => mb_substr((string) ($user?->username ?? ($credentials['username'] ?? '')), 0, 64),
                'ip_address' => $this->request->ip(),
                'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
                'succeeded' => $event instanceof Login,
                'method' => $this->method($event, $user),
            ]);
        } catch (Throwable) {
            // Never let the audit trail stand between a player and the city.
        }
    }

    private function method(Failed|Login $event, ?User $user): string
    {
        if ($event instanceof Failed) {
            return $user === null ? 'unknown-user' : 'wrong-password';
        }

        return $this->request->routeIs('two-factor.login') || $this->request->is('two-factor-challenge')
            ? 'password-2fa'
            : 'password';
    }
}
