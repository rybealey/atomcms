<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Discord account linking. The game client opens these pages in a new tab;
 * they share the CMS session, so `auth` middleware covers identity. The
 * user's OAuth token is used once (identify + guilds.join) and never stored.
 */
class DiscordController extends Controller
{
    public function __construct(
        private readonly DiscordApi $api,
        private readonly DiscordSyncService $sync,
    ) {}

    public function show(Request $request): View
    {
        return view('discord.status', [
            'linked' => (bool) $request->user()->discord_id,
            'linkedAt' => (int) ($request->user()->discord_linked_at ?? 0),
            'configured' => $this->api->configured(),
            'inviteUrl' => config('services.discord.invite_url'),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->api->configured()) {
            return redirect()->route('discord.show')
                ->with('message', __('Discord linking is not available right now. Please try again later.'));
        }

        if ($request->user()->discord_id) {
            return redirect()->route('discord.show');
        }

        $state = Str::random(40);
        $request->session()->put('discord_oauth_state', $state);

        return redirect()->away($this->api->authorizeUrl(route('discord.callback'), $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('discord_oauth_state');

        if (! $state || ! hash_equals($state, (string) $request->query('state', ''))) {
            return redirect()->route('discord.show')
                ->with('message', __('The Discord link attempt expired or was invalid. Please try again.'));
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            // User hit "Cancel" on the consent screen.
            return redirect()->route('discord.show')
                ->with('message', __('Discord linking was cancelled.'));
        }

        $token = $this->api->exchangeCode($code, route('discord.callback'));
        $identity = $token ? $this->api->identify($token['access_token']) : null;

        if (! $identity || empty($identity['id'])) {
            return redirect()->route('discord.show')
                ->with('message', __('Discord did not confirm the link. Please try again.'));
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
            return redirect()->route('discord.show')
                ->with('message', __('That Discord account is already linked to a different PixelRP account.'));
        }

        $user->discord_id = $discordId;
        $user->discord_linked_at = time();
        $user->save();

        // Auto-join the guild (no-op if already a member), then converge
        // nickname + roles right away.
        $this->api->joinGuild($discordId, $token['access_token']);
        $this->sync->syncUser($user->fresh());

        return redirect()->route('discord.show')
            ->with('success', __('Your Discord account is now connected.'));
    }

    public function unlink(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->discord_id) {
            $this->sync->unlinkUser($user);

            $user->discord_id = null;
            $user->discord_linked_at = 0;
            $user->save();
        }

        return redirect()->route('discord.show')
            ->with('success', __('Your Discord account has been disconnected.'));
    }
}
