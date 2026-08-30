<?php

use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordSyncService;

/**
 * unlinkById is the ONLY Discord-role cleanup path for a disconnected
 * account - discord:sweep can never reach it, since the emulator has
 * already cleared users.discord_id by the time this runs. A bug in the
 * role-diff math here strands a player's roles permanently and silently,
 * so exercise the real class (not a mock of it) against a mocked DiscordApi.
 */
it('unlinkById drops bot-managed roles, adds Unverified exactly once, keeps unmanaged roles, and clears the nick', function () {
    config()->set('services.discord.roles', [
        'verified' => 'role-verified',
        'unverified' => 'role-unverified',
        'online' => 'role-online',
        'vip' => 'role-vip',
        'staff' => 'role-staff',
        'donor' => null,
        'committee' => null,
    ]);

    $api = Mockery::mock(DiscordApi::class);
    $api->shouldReceive('configured')->andReturnTrue();
    $api->shouldReceive('getMember')->once()->with('123456789')->andReturn([
        'nick' => 'SomeNick',
        'roles' => ['role-verified', 'role-online', 'role-vip', 'role-unmanaged'],
    ]);

    $api->shouldReceive('updateMember')
        ->once()
        ->with('123456789', Mockery::on(function (array $payload) {
            expect($payload['nick'])->toBeNull();

            $roles = $payload['roles'];

            expect($roles)->toContain('role-unverified')
                ->and($roles)->toContain('role-unmanaged')
                ->and($roles)->not->toContain('role-verified')
                ->and($roles)->not->toContain('role-online')
                ->and($roles)->not->toContain('role-vip')
                ->and($roles)->not->toContain('role-staff');

            expect(array_count_values($roles)['role-unverified'])->toBe(1);

            return true;
        }));

    $sync = new DiscordSyncService($api);

    $sync->unlinkById('123456789');
});

it('unlinkById does nothing when the member has already left the guild', function () {
    config()->set('services.discord.roles', [
        'verified' => 'role-verified',
        'unverified' => 'role-unverified',
    ]);

    $api = Mockery::mock(DiscordApi::class);
    $api->shouldReceive('configured')->andReturnTrue();
    $api->shouldReceive('getMember')->once()->with('123456789')->andReturnNull();
    $api->shouldNotReceive('updateMember');

    $sync = new DiscordSyncService($api);

    $sync->unlinkById('123456789');
});
