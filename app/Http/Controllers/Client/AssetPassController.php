<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\AssetPass;
use App\Support\AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * pixelrp: renews the asset pass for a client that is still open. The client
 * calls this every half hour (client AssetPassRefresh), well inside the pass's
 * TTL, so a long session never meets an expired pass mid-load.
 */
class AssetPassController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $response = response()->noContent();

        foreach (AssetPass::issue(AuthenticatedUser::from($request), $request) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
