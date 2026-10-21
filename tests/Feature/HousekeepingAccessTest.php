<?php

use App\Models\User;
use App\Models\User\Ban;
use App\Models\WebsiteHousekeepingPermission;

function grantHousekeepingPermission(string $permission, int $minRank): void
{
    WebsiteHousekeepingPermission::query()->create([
        'permission' => $permission,
        'min_rank' => $minRank,
        'description' => "Testing {$permission}",
    ]);
}

test('guests are redirected away from the housekeeping panel', function () {
    installHotel();

    $this->get('/housekeeping')->assertRedirect();
});

test('users without the housekeeping permission are sent back to the website', function () {
    installHotel();

    $user = User::factory()->create();

    // A page request is redirected (EnsureHousekeepingAccess); the JSON API refuses outright.
    $this->actingAs($user)->get('/housekeeping')->assertRedirect(route('welcome'));
    $this->actingAs($user)->getJson('/housekeeping/api/me')->assertForbidden();
});

test('forced staff two-factor authentication also protects housekeeping', function () {
    installHotel();
    grantHousekeepingPermission('can_access_housekeeping', 6);
    setSetting('force_staff_2fa', '1');
    setSetting('min_staff_rank', '4');

    $staff = User::factory()->create(['rank' => 6]);

    $this->actingAs($staff)
        ->get('/housekeeping')
        ->assertRedirect(route('settings.two-factor'));
});

test('banned staff cannot access housekeeping', function () {
    installHotel();
    grantHousekeepingPermission('can_access_housekeeping', 6);
    setSetting('force_staff_2fa', '0');

    $staff = User::factory()->create(['rank' => 6]);
    Ban::create([
        'user_id' => $staff->id,
        'ip' => '',
        'machine_id' => '',
        'user_staff_id' => $staff->id,
        'timestamp' => time(),
        'ban_expire' => time() + 3600,
        'ban_reason' => 'Testing',
        'type' => 'account',
    ]);

    $this->actingAs($staff)
        ->get('/housekeeping')
        ->assertRedirect(route('banned.show'));
});

test('maintenance restrictions also protect housekeeping', function () {
    installHotel();
    grantHousekeepingPermission('can_access_housekeeping', 6);
    setSetting('maintenance_enabled', '1');
    setSetting('min_maintenance_login_rank', '7');
    setSetting('force_staff_2fa', '0');

    $staff = User::factory()->create(['rank' => 6]);

    $this->actingAs($staff)
        ->get('/housekeeping')
        ->assertRedirect(route('maintenance.show'));
});

test('staff can only act on players below their own rank', function () {
    installHotel();
    grantHousekeepingPermission('can_access_housekeeping', 6);
    grantHousekeepingPermission('manage_bans', 6);
    setSetting('force_staff_2fa', '0');

    $actor = User::factory()->create(['rank' => 6]);
    $equal = User::factory()->create(['rank' => 6]);

    $this->actingAs($actor)
        ->postJson("/housekeeping/api/players/{$equal->id}/ban", ['seconds' => 3600, 'reason' => 'Testing'])
        ->assertForbidden();
});
