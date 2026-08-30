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

it('skips an unlink row when the discord id has since been re-linked to a user, but still deletes it', function () {
    $api = Mockery::mock(DiscordApi::class);
    $api->shouldReceive('configured')->andReturnTrue();
    app()->instance(DiscordApi::class, $api);

    // The player disconnected and reconnected the same Discord account (or
    // someone else claimed the freed id) inside the drain's window, so this
    // id is linked again by the time the queued 'unlink' row is processed.
    $user = User::factory()->create(['discord_id' => '99887766554433221']);

    $sync = Mockery::mock(DiscordSyncService::class);
    $sync->shouldNotReceive('unlinkById');
    $sync->shouldNotReceive('syncUser');
    app()->instance(DiscordSyncService::class, $sync);

    DB::table('discord_sync_queue')->insert([
        'user_id' => $user->id,
        'discord_id' => '99887766554433221',
        'reason' => 'unlink',
        'created_at' => time(),
    ]);

    $this->artisan('discord:process')->assertSuccessful();

    // The stale row must not accumulate forever just because it was skipped.
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
