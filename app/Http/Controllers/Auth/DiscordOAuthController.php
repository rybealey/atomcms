<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Discord\ConnectionMetadataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Discord OAuth flow for "show Pixeltower on my Discord profile."
//
// Scopes:
//   identify              — required for /users/@me lookup
//   role_connections.write — required to PUT role-connection metadata
//
// The legacy in-game `:verify <code>` flow stays available; this is the
// upgrade path for users who want the connection card on their profile.
class DiscordOAuthController extends Controller
{
    private const STATE_KEY = 'discord_oauth_state';
    private const AUTHORIZE_URL = 'https://discord.com/api/oauth2/authorize';
    private const TOKEN_URL = 'https://discord.com/api/oauth2/token';
    private const ME_URL = 'https://discord.com/api/v10/users/@me';

    public function start(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        $params = http_build_query([
            'client_id' => config('services.discord.client_id'),
            'redirect_uri' => config('services.discord.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'identify role_connections.write',
            'state' => $state,
            'prompt' => 'consent',
        ]);

        return redirect()->away(self::AUTHORIZE_URL . '?' . $params);
    }

    public function callback(Request $request, ConnectionMetadataService $metadata): RedirectResponse
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('welcome')->with('error', __('You must be signed in to link Discord.'));
        }

        $expected = $request->session()->pull(self::STATE_KEY);
        if (!$expected || !hash_equals($expected, (string) $request->query('state', ''))) {
            return redirect()->route('me.show')->with('error', __('Discord link failed: invalid state.'));
        }

        if ($request->has('error')) {
            return redirect()->route('me.show')->with('error', __('Discord link cancelled.'));
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('me.show')->with('error', __('Discord link failed: no code.'));
        }

        $tokenResponse = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.discord.client_id'),
            'client_secret' => config('services.discord.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.discord.redirect_uri'),
        ]);

        if (!$tokenResponse->successful()) {
            Log::warning('discord oauth: token exchange failed', [
                'user_id' => $user->id,
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);
            return redirect()->route('me.show')->with('error', __('Discord link failed: token exchange.'));
        }

        $token = $tokenResponse->json();
        $accessToken = (string) ($token['access_token'] ?? '');
        $refreshToken = (string) ($token['refresh_token'] ?? '');
        $expiresIn = (int) ($token['expires_in'] ?? 0);
        $scope = (string) ($token['scope'] ?? '');

        if ($accessToken === '' || $refreshToken === '') {
            return redirect()->route('me.show')->with('error', __('Discord link failed: missing tokens.'));
        }

        $meResponse = Http::withToken($accessToken)->acceptJson()->get(self::ME_URL);
        if (!$meResponse->successful()) {
            return redirect()->route('me.show')->with('error', __('Discord link failed: profile lookup.'));
        }

        $me = $meResponse->json();
        $discordId = (string) ($me['id'] ?? '');
        $discordUsername = (string) ($me['username'] ?? '');
        if ($discordId === '') {
            return redirect()->route('me.show')->with('error', __('Discord link failed: no Discord id.'));
        }

        // If this Discord account is already linked to a *different* habbo,
        // refuse — don't silently steal the link. The user must first
        // /unlink in the bot or in-game.
        $existing = User::where('discord_id', $discordId)->where('id', '!=', $user->id)->first();
        if ($existing) {
            return redirect()->route('me.show')->with(
                'error',
                __('That Discord account is already linked to another Pixeltower user. Use /unlink in Discord first.')
            );
        }

        $user->discord_id = $discordId;
        $user->discord_username = $discordUsername;
        $user->discord_access_token = $accessToken;
        $user->discord_refresh_token = $refreshToken;
        $user->discord_token_expires_at = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;
        $user->discord_scopes = $scope;
        $user->discord_revoked_at = null;
        if (!$user->discord_verified_at) {
            $user->discord_verified_at = now();
        }
        $user->save();

        $metadata->sync($user->refresh());

        return redirect()->route('me.show')->with('success', __('Discord linked. Pixeltower will now appear on your profile.'));
    }

    public function disconnect(Request $request, ConnectionMetadataService $metadata): RedirectResponse
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('welcome');
        }

        if ($user->discord_id) {
            $metadata->clear($user);
        }

        $user->discord_access_token = null;
        $user->discord_refresh_token = null;
        $user->discord_token_expires_at = null;
        $user->discord_scopes = null;
        $user->discord_revoked_at = now();
        $user->save();

        return redirect()->route('me.show')->with('success', __('Discord disconnected from Pixeltower.'));
    }
}
