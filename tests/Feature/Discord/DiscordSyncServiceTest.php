<?php

use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordSyncService;
use Illuminate\Http\Client\Response;

/** A real HTTP client Response with the given status, as updateMember returns. */
function discordResponse(int $status): Response
{
    return new Response(new GuzzleHttp\Psr7\Response($status));
}

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
        }))
        ->andReturn(discordResponse(200));

    $sync = new DiscordSyncService($api);

    $sync->unlinkById('123456789');
});

/**
 * Discord never lets a bot rename the guild owner, and nick + roles travel
 * in ONE PATCH - so that request fails as a unit and the role changes ride
 * along with it, silently (the HTTP client does not throw on 4xx, so nothing
 * is even logged). syncUser() already retries roles-only on a 403; unlink
 * must do the same or the owner can never be unlinked.
 */
it('unlinkById still strips roles when the nick PATCH is rejected for the guild owner', function () {
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
        'nick' => 'OwnerNick',
        'roles' => ['role-verified', 'role-online', 'role-unmanaged'],
    ]);

    // First call carries the nick, and Discord rejects the whole thing.
    $api->shouldReceive('updateMember')
        ->once()
        ->with('123456789', Mockery::on(fn (array $payload) => array_key_exists('nick', $payload)))
        ->andReturn(discordResponse(403));

    // The retry must drop the nick and still take the roles off.
    $api->shouldReceive('updateMember')
        ->once()
        ->with('123456789', Mockery::on(function (array $payload) {
            expect($payload)->not->toHaveKey('nick');

            $roles = $payload['roles'];

            expect($roles)->toContain('role-unverified')
                ->and($roles)->toContain('role-unmanaged')
                ->and($roles)->not->toContain('role-verified')
                ->and($roles)->not->toContain('role-online');

            return true;
        }))
        ->andReturn(discordResponse(200));

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
