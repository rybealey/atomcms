<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Services\Roleplay\GangDirectory;
use App\Support\AuthenticatedUser;
use Illuminate\View\View;

/**
 * pixelrp: who runs which block. Standings, your own gang's position and the
 * districts every gang holds.
 */
class GangController extends Controller
{
    public function __invoke(GangDirectory $gangs): View
    {
        $character = AuthenticatedUser::current()->activeCharacter();
        $standings = $gangs->standings();
        $mine = $gangs->membership($character->id);
        $leader = $standings->first();

        return view('gangs.index', [
            'gangs' => $standings,
            'territories' => $gangs->territories(),
            'mine' => $mine,
            // how far your gang is behind the top spot, in blocks
            'gapToFirst' => $mine !== null && $leader !== null ? max(0, $leader->turf - $mine->turf) : null,
        ]);
    }
}
