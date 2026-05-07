<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Discord\ConnectionMetadataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Internal endpoint hit by the discord-bot service whenever a linked
// user comes online or goes offline in-game. The bot already maintains
// `onlineSet` over its WS bridge with the emulator; this is just the
// fan-out hop into our role-connection metadata pipeline.
//
// Auth: shared secret in X-Pixeltower-Bot-Token. Not Sanctum/Fortify —
// this is service-to-service inside the same private network.
class DiscordPresenceController extends Controller
{
    public function __invoke(Request $request, ConnectionMetadataService $metadata): JsonResponse|Response
    {
        $expected = (string) config('services.discord.presence_webhook_secret', '');
        $provided = (string) $request->header('X-Pixeltower-Bot-Token', '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'event' => 'required|string|in:online,offline',
            'discord_id' => 'required|string|max:32',
            'habbo_id' => 'sometimes|integer',
        ]);

        $user = User::where('discord_id', $data['discord_id'])->first();
        if (!$user) {
            return response()->json(['ok' => true, 'noted' => 'unlinked'], 200);
        }

        if (!$user->discord_access_token || $user->discord_revoked_at !== null) {
            // Linked via the legacy `:verify` flow but never granted the
            // OAuth scope. Nothing to push; not an error.
            return response()->json(['ok' => true, 'noted' => 'no_oauth'], 200);
        }

        $metadata->sync($user);
        return response()->json(['ok' => true], 200);
    }
}
