<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AssetPause;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * pixelrp: who is paused from game files right now (AssetPause), for the web
 * container's nginx only - it asks every 10s from inside its own container,
 * with the asset pass secret. One "<account id> <unix time it ends>" per
 * line. nginx turns away anyone else asking for /api/internal/ before it
 * ever gets here, and without the secret this answers 404 as well.
 */
class AssetPausesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.asset_pass.secret');
        $given = (string) ($request->headers->get('X-Asset-Pass-Secret') ?? '');

        if ($secret === '' || ! hash_equals($secret, $given)) {
            abort(404);
        }

        $lines = [];

        foreach (AssetPause::all() as $account => $until) {
            $lines[] = "{$account} {$until}";
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
