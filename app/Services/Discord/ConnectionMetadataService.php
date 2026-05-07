<?php

namespace App\Services\Discord;

use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Pushes per-user role connection metadata to Discord. This is what makes
// Pixeltower appear as a "Connection" card on the user's Discord profile,
// and what drives Linked Roles in the official guild.
//
// Discord docs:
//   PUT /users/@me/applications/{APP_ID}/role-connection
//   Token: user's OAuth bearer with scope role_connections.write
//
// Single entry point: ConnectionMetadataService::sync($user) — idempotent.
class ConnectionMetadataService
{
    private const DISCORD_API = 'https://discord.com/api/v10';

    public function sync(User $user): bool
    {
        if (!$user->discord_id || !$user->discord_access_token) {
            return false;
        }

        if ($user->discord_revoked_at !== null) {
            return false;
        }

        $token = $this->ensureFreshToken($user);
        if ($token === null) {
            return false;
        }

        $payload = [
            'platform_name' => config('services.discord.platform_name', 'Pixeltower'),
            'platform_username' => $this->buildPlatformUsername($user),
            'metadata' => $this->buildMetadata($user),
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->put(self::DISCORD_API . "/users/@me/applications/{$this->applicationId()}/role-connection", $payload);
        } catch (RequestException $e) {
            Log::warning('discord: metadata sync HTTP error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        if ($response->status() === 401) {
            // Token rejected even after refresh attempt — user has revoked
            // the grant from Discord settings. Mark and stop pushing.
            $this->markRevoked($user);
            return false;
        }

        if (!$response->successful()) {
            Log::warning('discord: metadata sync failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        $user->discord_metadata_synced_at = now();
        $user->save();
        return true;
    }

    public function clear(User $user): bool
    {
        if (!$user->discord_id || !$user->discord_access_token) {
            return false;
        }

        $token = $this->ensureFreshToken($user);
        if ($token === null) {
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->put(self::DISCORD_API . "/users/@me/applications/{$this->applicationId()}/role-connection", [
                    'platform_name' => config('services.discord.platform_name', 'Pixeltower'),
                    'platform_username' => mb_substr((string) $user->username, 0, 100),
                    'metadata' => [],
                ]);
        } catch (RequestException $e) {
            Log::warning('discord: metadata clear HTTP error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        return $response->successful();
    }

    private function buildPlatformUsername(User $user): string
    {
        // Discord's role-connection metadata is integer/boolean/datetime
        // only — there's no "string" metadata type. The motto therefore
        // rides on `platform_username`, which is the visible line under
        // the "Pixeltower" header on the profile card. We render it as
        // "username · motto" (or just "username" when motto is empty),
        // capped at the Discord-imposed 100-char limit.
        $username = (string) $user->username;
        $motto = (string) ($user->motto ?? '');
        $motto = trim(str_replace('&', 'and', $motto));

        if ($motto === '') {
            return mb_substr($username, 0, 100);
        }
        $combined = $username . ' · ' . $motto;
        return mb_substr($combined, 0, 100);
    }

    private function buildMetadata(User $user): array
    {
        $verifiedSince = $user->discord_verified_at instanceof Carbon
            ? $user->discord_verified_at->toIso8601String()
            : null;

        return array_filter([
            'online' => $user->online ? 1 : 0,
            'verified_since' => $verifiedSince,
            'hours_played' => $this->hoursPlayed($user),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    private function hoursPlayed(User $user): ?int
    {
        // Habbo's `users.account_created` and `last_online` are unix
        // timestamps. We don't track session minutes; an approximation
        // (account-age in days clamped down) is good enough for the
        // "Veteran" linked-role threshold.
        $created = (int) ($user->account_created ?? 0);
        if ($created <= 0) return null;
        $days = max(0, (int) floor((time() - $created) / 86400));
        return min($days * 1, 99999); // 1 nominal hour / day cap
    }

    private function applicationId(): string
    {
        $id = config('services.discord.application_id');
        if (!$id) {
            throw new \RuntimeException('DISCORD_APPLICATION_ID is not configured.');
        }
        return $id;
    }

    private function ensureFreshToken(User $user): ?string
    {
        $expiresAt = $user->discord_token_expires_at;
        if ($expiresAt instanceof Carbon && $expiresAt->copy()->subMinute()->isFuture()) {
            return $user->discord_access_token;
        }

        if (!$user->discord_refresh_token) {
            return null;
        }

        try {
            $response = Http::asForm()->post(self::DISCORD_API . '/oauth2/token', [
                'client_id' => config('services.discord.client_id'),
                'client_secret' => config('services.discord.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $user->discord_refresh_token,
            ]);
        } catch (RequestException $e) {
            Log::warning('discord: refresh token HTTP error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if ($response->status() === 401 || $response->status() === 400) {
            // Refresh token revoked by user. Mark and stop.
            $this->markRevoked($user);
            return null;
        }

        if (!$response->successful()) {
            Log::warning('discord: refresh token failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();
        $user->discord_access_token = (string) ($data['access_token'] ?? '');
        if (!empty($data['refresh_token'])) {
            $user->discord_refresh_token = (string) $data['refresh_token'];
        }
        if (isset($data['expires_in'])) {
            $user->discord_token_expires_at = now()->addSeconds((int) $data['expires_in']);
        }
        if (isset($data['scope'])) {
            $user->discord_scopes = (string) $data['scope'];
        }
        $user->save();
        return $user->discord_access_token;
    }

    private function markRevoked(User $user): void
    {
        $user->discord_revoked_at = now();
        $user->discord_access_token = null;
        $user->discord_refresh_token = null;
        $user->discord_token_expires_at = null;
        $user->save();
        Log::info('discord: marked OAuth grant revoked', ['user_id' => $user->id]);
    }
}
