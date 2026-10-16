<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\City\CityWeather;
use App\Services\Roleplay\CorporationDirectory;
use App\Services\Roleplay\GangDirectory;
use App\Services\Roleplay\PlayerProfile;
use App\Support\AuthenticatedUser;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * pixelrp: home. Your character, wallet and friends at a glance - for the
 * character the account is currently playing (the one `/game` enters as).
 */
class MeController extends Controller
{
    public function __invoke(
        PlayerProfile $profile,
        CorporationDirectory $corporations,
        GangDirectory $gangs,
        CityWeather $weather,
    ): View {
        $me = AuthenticatedUser::current()->activeCharacter();
        $gang = $gangs->membership($me->id);

        return view('user.me', [
            'me' => $me,
            'wallet' => $profile->wallet($me),
            'job' => $corporations->employment($me->id),
            'gang' => $gang,
            'gangMembers' => $gang !== null ? $gangs->sampleMembers($gang->id) : collect(),
            'stats' => $profile->stats($me),
            'friends' => $profile->friendsInTown($me),
            'friendCounts' => $profile->friendCounts($me),
            'sky' => $weather->current(),
            'hour' => (int) CarbonImmutable::now('America/Los_Angeles')->format('G'),
        ]);
    }
}
