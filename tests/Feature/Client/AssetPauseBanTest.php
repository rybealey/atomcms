<?php

use App\Models\User;
use App\Support\AssetPause;
use Illuminate\Support\Facades\DB;

/*
 * A ban on any character of an account pauses the whole account from game
 * files until the ban ends (AssetPause). Plus keys a user ban by the
 * character's name; a temporary table shadows the testing schema's Arcturus
 * one, as PlusHousekeepingTest does.
 */

beforeEach(function () {
    installHotel();

    DB::statement(<<<'SQL'
        CREATE TEMPORARY TABLE bans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bantype VARCHAR(10) NOT NULL,
            value VARCHAR(255) NOT NULL,
            reason VARCHAR(255) NOT NULL DEFAULT '',
            expire INT NOT NULL DEFAULT 0,
            added_by VARCHAR(50) NOT NULL DEFAULT '',
            added_date INT NOT NULL DEFAULT 0
        )
    SQL);
});

afterEach(function () {
    DB::statement('DROP TEMPORARY TABLE IF EXISTS bans');
});

test('a ban on a second character pauses the whole account until it ends', function () {
    $root = User::factory()->create(['username' => 'Marlowe']);
    User::factory()->create(['username' => 'Yavn', 'parent_id' => $root->id]);
    $expires = time() + 3600;

    DB::table('bans')->insert(['bantype' => 'user', 'value' => 'yavn', 'expire' => $expires]);

    expect(AssetPause::until($root))->toBe($expires)
        ->and(AssetPause::all())->toHaveKey($root->id, $expires);
});

test('an expired ban pauses nothing', function () {
    $root = User::factory()->create(['username' => 'Marlowe']);

    DB::table('bans')->insert(['bantype' => 'user', 'value' => 'Marlowe', 'expire' => time() - 60]);

    expect(AssetPause::until($root))->toBeNull()
        ->and(AssetPause::all())->not->toHaveKey($root->id);
});
