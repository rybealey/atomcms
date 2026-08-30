<?php

use App\Contracts\Rcon;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordSyncService;
use App\Services\FakeRcon;

it('links the account and pushes status over rcon on a successful callback', function () {
    $rcon = new FakeRcon(connected: true);
    app()->instance(Rcon::class, $rcon);

    $api = Mockery::mock(DiscordApi::class);
    $api->shouldReceive('configured')->andReturnTrue();
    $api->shouldReceive('exchangeCode')->once()->andReturn(['access_token' => 'tok']);
    $api->shouldReceive('identify')->once()->with('tok')->andReturn(['id' => '12345678901234567']);
    $api->shouldReceive('joinGuild')->once();
    app()->instance(DiscordApi::class, $api);

    $sync = Mockery::mock(DiscordSyncService::class);
    $sync->shouldReceive('syncUser')->once();
    app()->instance(DiscordSyncService::class, $sync);

    $user = User::factory()->create(['discord_id' => null]);

    $response = $this->actingAs($user)
        ->withSession(['discord_oauth_state' => 'state-token'])
        ->get('/discord/callback?code=abc&state=state-token');

    $response->assertOk()->assertSee('Discord connected');

    expect($user->fresh()->discord_id)->toBe('12345678901234567')
        ->and(collect($rcon->calls)->pluck('method'))->toContain('syncDiscordStatus');
});

it('reports an error inline when the oauth state does not match', function () {
    app()->instance(Rcon::class, new FakeRcon(connected: true));

    $user = User::factory()->create(['discord_id' => null]);

    $this->actingAs($user)
        ->withSession(['discord_oauth_state' => 'state-token'])
        ->get('/discord/callback?code=abc&state=wrong-token')
        ->assertOk()
        ->assertSee('Something went wrong');

    expect($user->fresh()->discord_id)->toBeNull();
});

it('no longer exposes the discord status page or unlink form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/discord')->assertNotFound();
    $this->actingAs($user)->post('/discord/unlink')->assertNotFound();
});
