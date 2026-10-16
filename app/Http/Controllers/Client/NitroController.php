<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\City\CityWeather;
use App\Support\AssetPass;
use App\Support\AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NitroController extends Controller
{
    public function __invoke(Request $request, CityWeather $weather): Response
    {
        $user = AuthenticatedUser::from($request);

        $user->update([
            'ip_current' => $request->ip(),
        ]);

        // pixelrp: the account enters the hotel as its ACTIVE character - the
        // last one played, or the root when there is only one. This is the
        // only place a character switch takes effect: the Wallet writes
        // active_character_id and reloads, and the next ticket is that
        // character's. activeCharacter() falls back to the root rather than
        // ever resolving to nothing, because this is the login path.
        //
        // The asset pass rides on this response, so it is set before the
        // client iframe asks for its first file (AssetPass).
        // The disconnected screen behind the client is the same live street
        // as the login page, so it is rendered with the page and shown later.
        $response = response()->view('client.nitro', [
            'sso' => $user->activeCharacter()->ssoTicket(),
            'sky' => $weather->current(),
        ]);

        foreach (AssetPass::issue($user, $request) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
