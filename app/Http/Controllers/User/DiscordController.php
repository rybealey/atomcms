<?php

namespace App\Http\Controllers\User;

use App\Contracts\Rcon;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Discord account linking. The game client opens `/discord/connect` in a
 * popup that shares the CMS session, so `auth` middleware covers identity.
 * The user's OAuth token is used once (identify + guilds.join) and never
 * stored.
 *
 * Disconnecting happens in-game (wire 3956), not here. The callback is the
 * only page in this flow: it reports the outcome and closes itself.
 */
class DiscordController extends Controller
{
    public function __construct(
        private readonly DiscordApi $api,
        private readonly DiscordSyncService $sync,
        private readonly Rcon $rcon,
    ) {}

    public function connect(Request $request): RedirectResponse|View
    {
        if (! $this->api->configured()) {
            return $this->result('error', __('Discord linking is not available right now. Please try again later.'));
        }

        if ($request->user()->discord_id) {
            return $this->result('success', __('Your Discord account is already connected.'));
        }

        $state = Str::random(40);
        $request->session()->put('discord_oauth_state', $state);

        return redirect()->away($this->api->authorizeUrl(route('discord.callback'), $state));
    }

    public function callback(Request $request): View
    {
        $state = $request->session()->pull('discord_oauth_state');

        if (! $state || ! hash_equals($state, (string) $request->query('state', ''))) {
            return $this->result('error', __('The Discord link attempt expired or was invalid. Please try again from the game.'));
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            // User hit "Cancel" on the consent screen.
            return $this->result('error', __('Discord linking was cancelled.'));
        }

        $token = $this->api->exchangeCode($code, route('discord.callback'));
        $identity = $token ? $this->api->identify($token['access_token']) : null;

        if (! $identity || empty($identity['id'])) {
            return $this->result('error', __('Discord did not confirm the link. Please try again from the game.'));
        }

        $user = $request->user();
        $discordId = (string) $identity['id'];

        // One Discord account per game account - hard uniqueness (also
        // enforced by the DB unique index as a race backstop).
        $taken = User::query()
            ->where('discord_id', $discordId)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($taken) {
            return $this->result('error', __('That Discord account is already linked to a different PixelRP account.'));
        }

        $user->discord_id = $discordId;
        $user->discord_linked_at = time();
        $user->save();

        // Auto-join the guild (no-op if already a member), then converge
        // nickname + roles right away.
        $this->api->joinGuild($discordId, $token['access_token']);
        $this->sync->syncUser($user->fresh());

        // Best effort: push the new status straight into the open client so
        // the Settings window updates without the player doing anything.
        $this->rcon->syncDiscordStatus($user);

        return $this->result('success', __('You can close this window. Your Settings page has already updated.'));
    }

    private function result(string $state, string $message): View
    {
        return view('discord.result', [
            'state' => $state,
            'message' => $message,
            'autoClose' => ($state === 'success'),
        ]);
    }
}
