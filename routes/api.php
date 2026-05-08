<?php

use App\Http\Controllers\Api\DeployStatusController;
use App\Http\Controllers\Api\HotelApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/user/{username}', [HotelApiController::class, 'fetchUser'])->name('api.fetch-user')->middleware('throttle:50,1');
Route::get('/online-users', [HotelApiController::class, 'onlineUsers'])->name('api.online-users')->middleware('throttle:50,1');
Route::get('/online-count', [HotelApiController::class, 'onlineUserCount'])->name('api.online-count')->middleware('throttle:50,1');

// Polled by the Nitro update overlay (see nitro-patches/260_deploymentOverlay.patch)
// during deploys. Reads storage/app/deploy-state.json — written by scripts/deploy.sh
// on the host (storage/app is bind-mounted into this container).
Route::get('/deploy-status', [DeployStatusController::class, 'show'])->name('api.deploy-status');
