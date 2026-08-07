<?php

namespace Database\Seeders;

use App\Models\Miscellaneous\WebsiteInstallation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TestingSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->ensureConnectedToATestDatabase();

        DB::table('users')->delete();

        WebsiteInstallation::query()->firstOrCreate(['installation_key' => 'key'], ['completed' => true]);

        $this->call([
            WebsiteSettingsSeeder::class,
            WebsiteLanguageSeeder::class,
            WebsitePermissionSeeder::class,
        ]);

        $this->createPlusEmulatorSchema();
    }

    /**
     * Defense in depth: this seeder opens by deleting every row in `users`,
     * which is destructive enough that a misconfigured environment pointing
     * it at the wrong database is a real incident, not a theoretical one -
     * see task-7-report.md "FIX ROUND 1" for exactly this happening because
     * a PHPUnit env override was silently ignored. Refuse to run unless we
     * are (a) in the testing app environment and (b) connected to a
     * database whose name unambiguously marks it as disposable.
     */
    private function ensureConnectedToATestDatabase(): void
    {
        $database = (string) DB::connection()->getDatabaseName();

        $looksLikeATestDatabase = $database === 'testing'
            || str_ends_with($database, '_test');

        if (! app()->environment('testing') || ! $looksLikeATestDatabase) {
            throw new RuntimeException(sprintf(
                'Refusing to run TestingSeeder against database "%s" in the "%s" environment. '
                . 'This seeder deletes every row in `users`; it must only run against a database '
                . 'named "testing" or ending in "_test" while APP_ENV=testing.',
                $database,
                app()->environment(),
            ));
        }
    }

    /**
     * The core SQL file ships the Arcturus schema; add the Plus EMU tables and
     * columns the Plus drivers touch, so both emulator drivers can be
     * conformance-tested against the one testing database.
     */
    private function createPlusEmulatorSchema(): void
    {
        if (! Schema::hasColumn('users', 'activity_points')) {
            Schema::table('users', function (Blueprint $table) {
                $table->integer('activity_points')->default(0);
                $table->integer('vip_points')->default(0);
                $table->integer('gotw_points')->default(0);
            });
        }

        if (! Schema::hasTable('user_stats')) {
            Schema::create('user_stats', function (Blueprint $table) {
                $table->integer('id')->primary()->comment('Plus keys user_stats by the user id');
                $table->integer('OnlineTime')->default(0);
                $table->integer('Respect')->default(0);
                $table->integer('AchievementScore')->default(0);
            });
        }

        if (! Schema::hasTable('user_badges')) {
            Schema::create('user_badges', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id')->index();
                $table->string('badge_id', 100);
                $table->integer('badge_slot')->default(0);
            });
        }
    }
}
