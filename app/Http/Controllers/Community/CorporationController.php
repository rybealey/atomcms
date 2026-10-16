<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Services\Roleplay\CorporationDirectory;
use App\Support\AuthenticatedUser;
use Illuminate\View\View;

/**
 * pixelrp: every job in the city, and one corporation in detail. Applying is
 * done in game at the corporation's HQ, so these pages only read.
 */
class CorporationController extends Controller
{
    public function __construct(private readonly CorporationDirectory $corporations) {}

    public function index(): View
    {
        $corporations = $this->corporations->all();
        $job = $this->corporations->employment(AuthenticatedUser::current()->activeCharacter()->id);

        return view('corporations.index', [
            'corporations' => $corporations,
            'employees' => (int) $corporations->sum('employees'),
            'openNow' => $corporations->where('on_duty', '>', 0)->count(),
            'myCorporationId' => $job?->corporation_id,
        ]);
    }

    public function show(int $id): View
    {
        $corporation = $this->corporations->find($id);

        abort_if($corporation === null, 404);

        $character = AuthenticatedUser::current()->activeCharacter();
        $job = $this->corporations->employment($character->id);

        return view('corporations.show', [
            'corporation' => $corporation,
            'job' => $job !== null && $job->corporation_id === $corporation->id ? $job : null,
            'characterId' => $character->id,
        ]);
    }
}
