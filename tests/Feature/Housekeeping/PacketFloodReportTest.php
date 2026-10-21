<?php

use App\Models\User;
use App\Support\Housekeeping\PacketFloodReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * rp_packet_flood_log belongs to the emulator (its SQL update 291), so the
 * testing schema has none; it is built here the way the emulator builds it.
 */

beforeEach(function () {
    installHotel();

    Schema::create('rp_packet_flood_log', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->string('username', 64)->default('');
        $table->string('packet', 64)->default('');
        $table->integer('dropped')->default(0);
        $table->integer('window_seconds')->default(0);
        $table->integer('created_at');
    });
});

afterEach(function () {
    Schema::dropIfExists('rp_packet_flood_log');
});

function floodRow(User $user, string $packet, int $dropped, int $at): array
{
    return ['user_id' => $user->id, 'username' => $user->username, 'packet' => $packet, 'dropped' => $dropped, 'window_seconds' => 10, 'created_at' => $at];
}

test('flood disconnects are read for every character on the account, latest first', function () {
    $root = User::factory()->create(['username' => 'Marlowe']);
    $child = User::factory()->create(['username' => 'Yavn', 'parent_id' => $root->id]);
    $stranger = User::factory()->create(['username' => 'Stranger']);

    DB::table('rp_packet_flood_log')->insert([
        floodRow($root, 'ChatEvent', 312, 1_800_000_000),
        floodRow($child, 'RpCatalogSearchEvent', 300, 1_800_000_100),
        floodRow($stranger, 'ChatEvent', 300, 1_800_000_200),
    ]);

    $expected = [
        ['character' => 'Yavn', 'packet' => 'RpCatalogSearchEvent', 'dropped' => 300, 'window' => 10, 'at' => 1_800_000_100],
        ['character' => 'Marlowe', 'packet' => 'ChatEvent', 'dropped' => 312, 'window' => 10, 'at' => 1_800_000_000],
    ];

    // The same list whichever of the account's characters the page is opened on.
    expect(PacketFloodReport::disconnects($root))->toBe($expected)
        ->and(PacketFloodReport::disconnects($child))->toBe($expected);
});

test('an account never disconnected for flooding has an empty list', function () {
    expect(PacketFloodReport::disconnects(User::factory()->create()))->toBe([]);
});
