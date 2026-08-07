<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\CreatesApplication;

/**
 * Verifies Atom's Fortify registration produces a `users` row PlusEMU accepts
 * (see emulator/Resources/SQLs/'Original Database.sql') and that
 * User::ssoTicket() mints an auth_ticket PlusEMU's Authenticator will honour.
 *
 * Deliberately does NOT extend Tests\TestCase: that base class pulls in
 * RefreshDatabase, which runs `migrate:fresh` against whatever database
 * phpunit.xml points at. Doing that against pixelrp_test would drop the
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
        // same fixtures the arcturus feature suite relies on (installation
        // marked complete, website settings, permissions). Idempotent
        // (firstOrCreate) and guarded against re-creating tables the PlusEMU
        // dump already provides, so it is safe to run before every test.
        $this->seed(TestingSeeder::class);
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
