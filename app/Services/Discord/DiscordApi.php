<?php

namespace App\Services\Discord;

use App\Support\OutboundHttp;
use Illuminate\Http\Client\Response;

/**
 * Thin REST client for the two Discord surfaces this integration uses:
 * the OAuth2 token/identify flow (user consents once, we never store their
 * token) and bot-token guild-member calls (nickname, roles, guilds.join).
 *
 * No gateway connection - everything is plain REST, so this runs fine from
 * web requests and the scheduler alike.
 */
class DiscordApi
{
    private const API = 'https://discord.com/api/v10';

    public function configured(): bool
    {
        return (bool) (config('services.discord.client_id')
            && config('services.discord.client_secret')
            && config('services.discord.bot_token')
            && config('services.discord.guild_id'));
    }

    /** Authorize URL for the identify + guilds.join consent screen. */
    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return 'https://discord.com/oauth2/authorize?' . http_build_query([
            'client_id' => config('services.discord.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'identify guilds.join',
            'state' => $state,
            'prompt' => 'consent',
        ]);
    }

    /** Exchange the callback code for a short-lived access token. */
    public function exchangeCode(string $code, string $redirectUri): ?array
    {
        $response = OutboundHttp::request()
            ->asForm()
            ->post(self::API . '/oauth2/token', [
                'client_id' => config('services.discord.client_id'),
                'client_secret' => config('services.discord.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        return $response->successful() ? $response->json() : null;
    }

    /** The consenting user's own Discord account (id, username, ...). */
    public function identify(string $accessToken): ?array
    {
        $response = OutboundHttp::request()
            ->withToken($accessToken)
            ->get(self::API . '/users/@me');

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Put the user in the guild via guilds.join (201 joined, 204 already a
     * member). Needs their OAuth token once; the bot token authorizes it.
     */
    public function joinGuild(string $discordId, string $accessToken): bool
    {
        $response = $this->bot()->put($this->memberUrl($discordId), [
            'access_token' => $accessToken,
        ]);

        return $response->successful();
    }

    /** Guild member (nick, roles, ...), or null if not in the guild. */
    public function getMember(string $discordId): ?array
    {
        $response = $this->bot()->get($this->memberUrl($discordId));

        return $response->successful() ? $response->json() : null;
    }

    /** PATCH nick and/or full roles array. */
    public function updateMember(string $discordId, array $payload): Response
    {
        return $this->bot()->patch($this->memberUrl($discordId), $payload);
    }

    private function bot(): \Illuminate\Http\Client\PendingRequest
    {
        return OutboundHttp::request()
            ->withHeaders(['Authorization' => 'Bot ' . config('services.discord.bot_token')]);
    }

    private function memberUrl(string $discordId): string
    {
        return self::API . '/guilds/' . config('services.discord.guild_id') . '/members/' . $discordId;
    }
}
