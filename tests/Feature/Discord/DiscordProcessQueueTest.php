<?php

use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordSyncService;
use Illuminate\Support\Facades\DB;

it('strips roles for unlink rows using the queued discord id', function () {
    $api = Mockery::mock(DiscordApi::class);
    $api->shouldReceive('configured')->andReturnTrue();
    app()->instance(DiscordApi::class, $api);

    $sync = Mockery::mock(DiscordSyncService::class);
    $sync->shouldReceive('unlinkById')->once()->with('99887766554433221');
    $sync->shouldNotReceive('syncUser');
    app()->instance(DiscordSyncService::class, $sync);

    DB::table('discord_sync_queue')->insert([
        'user_id' => 1,
        'discord_id' => '99887766554433221',
        'reason' => 'unlink',
        'created_at' => time(),
    ]);

    $this->artisan('discord:process')->assertSuccessful();

    expect(DB::table('discord_sync_queue')->count())->toBe(0);
});

it('still syncs ordinary rows for linked users', function () {
    $api = Mockery::mock(DiscordApi::class);
    $api->shouldReceive('configured')->andReturnTrue();
    app()->instance(DiscordApi::class, $api);

    $user = User::factory()->create(['discord_id' => '11223344556677889']);

    $sync = Mockery::mock(DiscordSyncService::class);
    $sync->shouldReceive('syncUser')->once();
    $sync->shouldNotReceive('unlinkById');
    app()->instance(DiscordSyncService::class, $sync);

    DB::table('discord_sync_queue')->insert([
        'user_id' => $user->id,
        'discord_id' => null,
        'reason' => 'login',
        'created_at' => time(),
    ]);

    $this->artisan('discord:process')->assertSuccessful();

    expect(DB::table('discord_sync_queue')->count())->toBe(0);
});
