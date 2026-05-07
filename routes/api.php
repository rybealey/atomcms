<?php

use App\Http\Controllers\Api\DiscordPresenceController;
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

// Internal: discord-bot pushes presence here on online/offline.
// Auth via shared secret; rate-limited to avoid an unbounded loop.
Route::post('/internal/discord/presence', DiscordPresenceController::class)
    ->name('api.internal.discord.presence')
    ->middleware('throttle:600,1');
