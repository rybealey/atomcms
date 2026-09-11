<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NitroController extends Controller
{
    public function __invoke(Request $request): View
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
        return view('client.nitro', [
            'sso' => $user->activeCharacter()->ssoTicket(),
        ]);
    }
}
