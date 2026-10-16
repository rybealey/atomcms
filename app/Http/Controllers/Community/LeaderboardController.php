<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Services\Community\LeaderboardService;
use App\Support\AuthenticatedUser;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function __invoke(LeaderboardService $leaderboards): View
    {
        $me = AuthenticatedUser::current()->activeCharacter();

        return view('leaderboard', [
            'me' => $me,
            'categories' => $leaderboards->categories($me),
        ]);
    }
}
