<?php

namespace App\Services\Discord;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Idempotent "make Discord match the game" step for one linked user:
 * nickname = in-game name, plus the managed roles (Verified always while
 * linked, Online while the player is in-game, VIP while vip_expire is in
 * the future, Staff while rank 5+). Roles this bot does NOT manage are
 * never touched.
 *
 * Called from the OAuth callback, the queue drainer (login/logout/VIP
 * events enqueued by the emulator), and the 10-minute reconciliation
 * sweep - so a missed event only means a short delay, never drift.
 */
class DiscordSyncService
{
    public function __construct(
        private readonly DiscordApi $api,
    ) {}

    public function syncUser(User $user): void
    {
        if (! $user->discord_id || ! $this->api->configured()) {
            return;
        }

        try {
            $member = $this->api->getMember($user->discord_id);

            if ($member === null) {
                // Left the guild (or never joined). Nothing to manage; the
                // link stays so roles come back if they rejoin.
                return;
            }

            $payload = [];

            // Discord nicks cap at 32 chars; in-game names are well under.
            $nick = mb_substr($user->username, 0, 32);

            if (($member['nick'] ?? null) !== $nick) {
                $payload['nick'] = $nick;
            }

            $roles = $this->desiredRoles($user, $member['roles'] ?? []);

            if ($roles !== null) {
                $payload['roles'] = $roles;
            }

            if ($payload !== []) {
                $response = $this->api->updateMember($user->discord_id, $payload);

                // 403 on the guild owner's nick is expected - the bot can
                // never rename the owner. Retry with roles only.
                if ($response->status() === 403 && isset($payload['nick'], $payload['roles'])) {
                    $this->api->updateMember($user->discord_id, ['roles' => $payload['roles']]);
                }
            }

            $this->syncBadgesFromRoles($user, $member['roles'] ?? []);
        } catch (Throwable $e) {
            // Best-effort: the sweep re-converges. Log so repeated failures
            // (bad token, missing permission) are visible.
            Log::warning('Discord sync failed.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Strip the managed roles the bot controls when unlinking (and clear the
     * nickname it set). Other roles and the membership itself are untouched.
     */
    public function unlinkUser(User $user): void
    {
        if (! $user->discord_id || ! $this->api->configured()) {
            return;
        }

        try {
            $member = $this->api->getMember($user->discord_id);

            if ($member !== null) {
                $managed = array_filter(config('services.discord.roles'));
                $kept = array_values(array_diff($member['roles'] ?? [], $managed));

                $this->api->updateMember($user->discord_id, [
                    'nick' => null,
                    'roles' => $kept,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Discord unlink cleanup failed.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Full desired roles array (current roles with only the bot-managed
     * subset corrected), or null when nothing needs to change.
     */
    private function desiredRoles(User $user, array $currentRoles): ?array
    {
        $roleIds = config('services.discord.roles');

        $wanted = [
            'verified' => true,
            'online' => (bool) $user->online,
            'vip' => ((int) ($user->vip_expire ?? 0)) > time(),
            // Hotel staff (rank 5+) carry the Discord Staff role; a demotion
            // strips it on the next queue drain or sweep.
            'staff' => ((int) $user->rank) >= 5,
        ];

        $roles = $currentRoles;

        foreach ($wanted as $key => $shouldHave) {
            $roleId = $roleIds[$key] ?? null;

            if (! $roleId) {
                continue; // role not configured yet - skip, never remove
            }

            $has = in_array($roleId, $roles, true);

            if ($shouldHave && ! $has) {
                $roles[] = $roleId;
            } elseif (! $shouldHave && $has) {
                $roles = array_values(array_diff($roles, [$roleId]));
            }
        }

        return $roles === $currentRoles ? null : array_values($roles);
    }

    /**
     * Read side: Donor / Committee Member roles granted on Discord will map
     * to in-game badges. Foundation stub - wired up when those perks land.
     */
    private function syncBadgesFromRoles(User $user, array $currentRoles): void
    {
        // Intentionally empty for now.
    }
}
