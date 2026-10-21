<?php

use App\Models\User;
use App\Models\WebsiteHousekeepingPermission;
use App\Services\HousekeepingPermissionsService;
use Illuminate\Support\Facades\DB;

/*
 * The housekeeping panel's own gates and the writes that need nothing from
 * the emulator: the screen capabilities, saved views and the audit trail.
 * The roleplay screens are exercised against a PlusEMU database in
 * tests/Feature/Plus/HousekeepingApiTest.
 */

beforeEach(function () {
    installHotel();
    setSetting('force_staff_2fa', '0');
});

function housekeepingStaff(int $rank = 6, array $capabilities = []): User
{
    foreach (array_merge(['can_access_housekeeping'], $capabilities) as $permission) {
        WebsiteHousekeepingPermission::query()->updateOrCreate(['permission' => $permission], ['min_rank' => $rank, 'description' => 'Testing']);
    }

    HousekeepingPermissionsService::clearCache();

    return User::factory()->create(['rank' => $rank]);
}

test('the session endpoint describes the panel', function () {
    $staff = housekeepingStaff();

    $this->actingAs($staff)->getJson('/housekeeping/api/me')
        ->assertOk()
        ->assertJsonStructure(['user' => ['id', 'username', 'rank'], 'permissions', 'abilities', 'ranks', 'config' => ['environment', 'imager'], 'pulse' => ['counts', 'emulator']])
        ->assertJsonPath('config.environment', 'local');
});

test('a screen refuses a rank without its capability', function () {
    $staff = housekeepingStaff(5);
    WebsiteHousekeepingPermission::query()->updateOrCreate(['permission' => 'view_activity_logs'], ['min_rank' => 6, 'description' => 'Testing']);
    WebsiteHousekeepingPermission::query()->updateOrCreate(['permission' => 'manage_housekeeping_permissions'], ['min_rank' => 6, 'description' => 'Testing']);
    HousekeepingPermissionsService::clearCache();

    $this->actingAs($staff)->getJson('/housekeeping/api/logs')->assertForbidden();
    $this->actingAs($staff)->getJson('/housekeeping/api/audit')->assertForbidden();
    $this->actingAs($staff)->getJson('/housekeeping/api/access')->assertForbidden();
});

test('every path under the panel serves the app and unknown api paths are json 404s', function () {
    $staff = housekeepingStaff();

    $this->actingAs($staff)->get('/housekeeping/players/1/timeline')->assertOk()->assertSee('id="housekeeping"', false);
    $this->actingAs($staff)->getJson('/housekeeping/api/nothing-here')->assertNotFound();
});

test('the old panel is gone', function () {
    $staff = housekeepingStaff();

    // /housekeeping/legacy now lands on the app, which redirects unknown screens to Today.
    $this->actingAs($staff)->get('/housekeeping/legacy')->assertOk()->assertSee('id="housekeeping"', false);
});

test('saved views belong to their owner and shared ones are seen by everyone', function () {
    $owner = housekeepingStaff(6, ['view_activity_logs']);
    $other = User::factory()->create(['rank' => 6]);

    $id = $this->actingAs($owner)->postJson('/housekeeping/api/views', ['name' => 'Trades over 10k', 'filters' => ['kind' => 'trade', 'when' => '7d', 'junk' => 'x'], 'shared' => true])
        ->assertOk()
        ->json('id');

    expect(json_decode((string) DB::table('website_housekeeping_saved_views')->where('id', $id)->value('filters'), true))->toBe(['kind' => 'trade', 'when' => '7d']);

    // A second staff member in the same test needs a fresh session: the
    // session guard signs out a user whose password hash doesn't match it.
    $this->flushSession();
    $this->actingAs($other)->getJson('/housekeeping/api/views')->assertOk()->assertJsonPath('views.0.name', 'Trades over 10k')->assertJsonPath('views.0.mine', false);
    $this->actingAs($other)->putJson("/housekeeping/api/views/{$id}", ['name' => 'Mine now', 'filters' => []])->assertForbidden();
    $this->flushSession();
    $this->actingAs($owner)->deleteJson("/housekeeping/api/views/{$id}")->assertOk();

    expect(DB::table('activity_log')->where('log_name', 'housekeeping')->where('event', 'saved_view')->count())->toBe(2);
});

test('a website setting change keeps its reason in the history', function () {
    $staff = housekeepingStaff(6, ['manage_website_settings']);
    setSetting('hotel_name', 'Before');

    $this->actingAs($staff)->putJson('/housekeeping/api/settings', ['settings' => ['hotel_name' => 'After'], 'reason' => 'Rebrand'])->assertOk();

    $this->actingAs($staff)->getJson('/housekeeping/api/settings/changes')
        ->assertOk()
        ->assertJsonPath('changes.0.key', 'hotel_name')
        ->assertJsonPath('changes.0.old', 'Before')
        ->assertJsonPath('changes.0.new', 'After')
        ->assertJsonPath('changes.0.reason', 'Rebrand');
});
