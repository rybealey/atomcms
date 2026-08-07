<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoreSqlFile extends Migration
{
    public function up(): void
    {
        // The plus driver's test database is pre-seeded from PlusEMU's own
        // dump (see pixelrp_test setup); importing the Arcturus schema here
        // would clobber it. Schema::hasTable('users') already short-circuits
        // once that dump is present, but we guard on the driver explicitly
        // too so this migration can never run against a plus-driver database.
        if (! app()->environment('testing') || config('emulator.driver') === 'plus' || Schema::hasTable('users')) {
            return;
        }

        $path = database_path('migrations/sqls/default.sql');

        if (! is_readable($path)) {
            throw new RuntimeException('Unable to read the core SQL schema.');
        }

        $file = new SplFileObject($path);
        $statement = '';

        foreach ($file as $line) {
            $statement .= $line;

            if (! str_ends_with(rtrim($line), ';')) {
                continue;
            }

            DB::unprepared($statement);
            $statement = '';
        }

        if (trim($statement) !== '') {
            throw new RuntimeException('The core SQL schema ends with an incomplete statement.');
        }

        DB::statement('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        // The emulator owns this imported schema, so Laravel must not drop it.
    }
}
