<?php

namespace Tests\Feature\Plus;

use App\Models\Miscellaneous\WebsiteInstallation;
use App\Models\User;
use Database\Seeders\WebsiteLanguageSeeder;
use Database\Seeders\WebsitePermissionSeeder;
use Database\Seeders\WebsiteSettingsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\CreatesApplication;

/**
 * Verifies Atom's Fortify registration produces a `users` row PlusEMU accepts
 * (see emulator/Resources/SQLs/'Original Database.sql') and that
 * User::ssoTicket() mints an auth_ticket PlusEMU's Authenticator will honour.
 *
 * Lives in tests/Feature/Plus/ and is only ever run via phpunit.plus.xml
 * (see that file and cms/README or task-7-report.md "FIX ROUND 1" for the
 * documented invocation). The default phpunit.xml testsuite explicitly
 * <exclude>s this directory - running it under the default config would
 * point it at the arcturus-schema "testing" database instead of the
 * PlusEMU-schema "pixelrp_test" database and fail outright.
 *
 * Deliberately does NOT extend Tests\TestCase: that base class pulls in
 * RefreshDatabase, which runs `migrate:fresh` against whatever database
 * phpunit.plus.xml points at. Doing that against pixelrp_test would drop the
 * PlusEMU schema imported from the emulator's own dump - cms migrations
 * alone cannot recreate the `users` table's Plus-specific shape. This class
 * extends Laravel's base TestCase directly and uses DatabaseTransactions
 * instead, so every test runs inside a rolled-back transaction against the
 * schema already seeded into pixelrp_test (see task-7-report.md for the
 * one-time setup: `php artisan migrate --force` run manually against that
 * database before these tests exist).
 */
class PlusRegistrationTest extends \Illuminate\Foundation\Testing\TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // The registration route sits behind InstallationMiddleware; seed the
        // same fixtures the stock feature suite relies on (installation
        // marked complete, website settings, permissions) directly, rather
        // than via Database\Seeders\TestingSeeder. TestingSeeder's
        // createPlusEmulatorSchema() stubs Plus-shaped tables onto the
        // *arcturus* testing database for cross-driver dataset tests (see
        // e.g. tests/Feature/Emulator/BadgeRepositoryTest's "plus" dataset)
        // - it assumes a table literally named `user_stats`. PlusEMU's own
        // dump renames that table to `user_statistics` as part of its own
        // embedded migration script (see Original Database.sql around line
        // 25066), so against pixelrp_test, TestingSeeder's hasTable('user_stats')
        // guard sees it as "missing" and creates a bogus 4-column stub
        // alongside the real 20-column `user_statistics` table. Calling the
        // three fixture seeders individually avoids that (and avoids the
        // unconditional `DB::table('users')->delete()` at the top of
        // TestingSeeder::run(), which is unnecessary here - pixelrp_test's
        // `users` table is already empty at the start of each transaction).
        WebsiteInstallation::query()->firstOrCreate(['installation_key' => 'key'], ['completed' => true]);
        $this->seed(WebsiteSettingsSeeder::class);
        $this->seed(WebsiteLanguageSeeder::class);
        $this->seed(WebsitePermissionSeeder::class);
    }

    public function test_registered_user_row_is_plusemu_valid(): void
    {
        $response = $this->post('/register', [
            'username' => 'testduck',
            'mail' => 'duck@example.com',
            'password' => 'Secret-Password-1',
            'password_confirmation' => 'Secret-Password-1',
            'terms' => true,
        ]);

        $response->assertStatus(302);

        $user = User::where('username', 'testduck')->firstOrFail();

        $this->assertContains($user->gender, ['M', 'F']);
        $this->assertLessThanOrEqual(12, strlen((string) $user->account_created));
        $this->assertNotNull($user->look);
        $this->assertSame('', (string) $user->auth_ticket);
    }

    public function test_sso_ticket_is_plusemu_acceptable(): void
    {
        $user = User::factory()->create();
        $ticket = $user->ssoTicket();

        $this->assertGreaterThanOrEqual(15, strlen($ticket));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'auth_ticket' => $ticket]);
    }
}
