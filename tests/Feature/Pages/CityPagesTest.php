<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    installHotel();

    // The live sky is fetched from Open-Meteo on the guest pages; never from a test.
    Http::fake(['api.open-meteo.com/*' => Http::response(['current' => ['weather_code' => 45, 'temperature_2m' => 57.2, 'is_day' => 0]])]);
    Cache::flush();
});

test('the guest pages render over the live street', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('data-scene', false);
})->with([
    'landing' => 'welcome',
    'register' => 'register',
    'forgot password' => 'forgot.password.get',
]);

test('the city pages render for a signed-in player', function (string $route) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertOk();
})->with([
    'home' => 'me.show',
    'corporations' => 'corporations.index',
    'gangs' => 'gangs.index',
    'leaderboard' => 'leaderboard.index',
]);

test('an unknown corporation is a 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('corporations.show', 999999))
        ->assertNotFound();
});

test('guests are sent to log in from the city pages', function (string $route) {
    $this->get(route($route))->assertRedirect();
})->with([
    'corporations' => 'corporations.index',
    'gangs' => 'gangs.index',
]);

test('the game page carries the disconnected screen', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('nitro-client'))
        ->assertOk()
        ->assertSee('id="disconnected"', false)
        ->assertSee('session.replaced', false)
        ->assertSee('idle.timeout', false)
        ->assertSee('server.restart', false);
});
