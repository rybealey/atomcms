<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\AssetPass;
use App\Support\AssetPause;
use App\Support\AuthenticatedUser;
use App\Support\ConnectionLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NitroController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = AuthenticatedUser::from($request);

        $user->update([
            'ip_current' => $request->ip(),
        ]);

        // pixelrp: every entry is logged for staff - this IP and device
        // (ConnectionLog), paused or not; the page's browser then reports its
        // specs onto the same row. The device cookie rides on either response.
        $deviceId = ConnectionLog::deviceId($request) ?? ConnectionLog::newDeviceId();
        ConnectionLog::record($user, $request, $deviceId);

        // pixelrp: the browser guard sent this account back to the website a
        // moment ago (AssetPause). No ticket and no pass until the pause ends -
        // the page shows the ordinary "Connection lost" screen, with no reason.
        if (AssetPause::until($user) !== null) {
            return response()->view('client.nitro', [
                'sso' => null,
                'paused' => true,
            ])->withCookie(ConnectionLog::deviceCookie($deviceId, $request));
        }

        // pixelrp: the account enters the hotel as its ACTIVE character - the
        // last one played, or the root when there is only one. This is the
        // only place a character switch takes effect: the Wallet writes
        // active_character_id and reloads, and the next ticket is that
        // character's. activeCharacter() falls back to the root rather than
        // ever resolving to nothing, because this is the login path.
        //
        // The asset pass rides on this response, so it is set before the
        // client iframe asks for its first file (AssetPass).
        // The disconnected screen is rendered with the page and shown later.
        $response = response()->view('client.nitro', [
            'sso' => $user->activeCharacter()->ssoTicket(),
        ]);

        foreach (AssetPass::issue($user, $request) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response->withCookie(ConnectionLog::deviceCookie($deviceId, $request));
    }
}
