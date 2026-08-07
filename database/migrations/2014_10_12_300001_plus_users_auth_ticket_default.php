<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PlusEMU's own dump defines `users.auth_ticket` as `varchar(60) NOT NULL`
 * with no default (it is part of the composite primary key alongside `id`).
 * Arcturus's schema gives the equivalent column `DEFAULT ''`; Plus does not.
 *
 * Atom's registration path (App\Actions\Fortify\CreateNewUser) always writes
 * `auth_ticket => ''` explicitly, so it is unaffected. But anything that
 * creates a User without naming every column - notably
 * database/factories/UserFactory.php, which the whole test suite (and any
 * future seeding/tooling) relies on - fails with "Field 'auth_ticket'
 * doesn't have a default value" on the plus driver. Discovered via
 * tests/Feature/PlusRegistrationTest.php's SSO round-trip test.
 *
 * This is a separate migration rather than an addition to
 * 2014_10_12_300000_plus_users_compatibility_columns.php because that
 * migration has already executed everywhere this schema exists; a new
 * migration is what actually reaches those databases via `php artisan
 * migrate`, whereas editing an already-run migration's body would not.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('emulator.driver') !== 'plus') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_ticket', 60)->default('')->change();
        });
    }

    public function down(): void
    {
    }
};
