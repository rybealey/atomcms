<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\AssetPass;
use App\Support\AssetPause;
use App\Support\AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * pixelrp: renews the asset pass for a client that is still open. The client
 * calls this every half hour (client AssetPassRefresh), well inside the pass's
 * TTL, so a long session never meets an expired pass mid-load. An account
 * the browser guard has paused (AssetPause) gets a 403 and no fresh pass.
 */
class AssetPassController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = AuthenticatedUser::from($request);

        if (AssetPause::until($user) !== null) {
            return response()->noContent(403);
        }

        $response = response()->noContent();

        foreach (AssetPass::issue($user, $request) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
