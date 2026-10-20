<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\AuthenticatedUser;
use App\Support\ConnectionLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * pixelrp: the game page's report about the browser and machine it runs on
 * (the script in client/nitro.blade.php), kept for staff with the visit /game
 * just recorded (ConnectionLog). Only the device cookie /game set is trusted
 * to name the device.
 */
class DeviceReportController extends Controller
{
    private const MAX_BYTES = 16384;

    public function __invoke(Request $request): Response
    {
        $user = AuthenticatedUser::from($request);
        $deviceId = ConnectionLog::deviceId($request);

        if ($deviceId === null || strlen((string) $request->getContent()) > self::MAX_BYTES) {
            return response()->noContent(422);
        }

        ConnectionLog::attachSpecs($user, $request, $deviceId, ConnectionLog::cleanSpecs($request->json()->all()));

        return response()->noContent();
    }
}
