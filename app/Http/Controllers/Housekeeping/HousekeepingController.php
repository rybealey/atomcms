<?php

namespace App\Http\Controllers\Housekeeping;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Serves the housekeeping single-page app. Every path under /housekeeping
 * (except the JSON API and the legacy Filament panel) lands here; the React
 * router takes over from the URL.
 */
class HousekeepingController extends Controller
{
    public function __invoke(): View
    {
        return view('housekeeping.app');
    }
}
