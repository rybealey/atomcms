<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The gang tables belong to the emulator (its SQL updates 64, 65, 169 and
 * 221), so the testing schema has none of them and the page was otherwise
 * only exercised empty - which is how a room column that exists only in the
 * Arcturus schema reached beta as a 500. These tests build the same tables, fill a city with
 * two gangs and a held block, and render the page the way beta does.
 */

beforeEach(function () {
    installHotel();
    Cache::flush();

    // PlusEMU titles a room in `caption`; the testing schema (Arcturus) in
    // `name`. The page is read the way beta reads it.
    Schema::table('rooms', function (Blueprint $table) {
        $table->string('caption', 100)->default('Room');
    });

    Schema::create('groups', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name', 50);
        $table->string('desc', 255);
        $table->string('badge', 50);
        $table->unsignedInteger('owner_id');
        $table->integer('created');
        $table->enum('is_gang', ['0', '1'])->default('0');
        $table->integer('gang_level')->default(1);
        $table->integer('gang_xp')->default(0);
    });

    Schema::create('rp_gang_roles', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('gang_id');
        $table->string('name', 29);
        $table->integer('sort_order')->default(0);
    });

    Schema::create('rp_gang_members', function (Blueprint $table) {
        $table->unsignedInteger('gang_id');
        $table->integer('user_id');
        $table->integer('role_id')->nullable();
        $table->integer('joined_at');
        $table->primary(['gang_id', 'user_id']);
    });

    Schema::create('rp_turfs', function (Blueprint $table) {
        $table->integer('room_id')->primary();
        $table->integer('gang_id');
        $table->integer('claimed_by')->default(0);
        $table->integer('claimed_at')->default(0);
    });
});

afterEach(function () {
    Schema::table('rooms', function (Blueprint $table) {
        $table->dropColumn('caption');
    });
    Schema::dropIfExists('rp_turfs');
    Schema::dropIfExists('rp_gang_members');
    Schema::dropIfExists('rp_gang_roles');
    Schema::dropIfExists('groups');
});

/**
 * Two gangs: the Dockside Crew (the player's, holding one block) and the
 * Hilltop Kings (no turf, a higher level).
 *
 * @return array{me: User, boss: User}
 */
function foundGangs(): array
{
    $me = User::factory()->create();
    $boss = User::factory()->create(['online' => '1']);

    DB::table('groups')->insert([
        ['id' => 11, 'name' => 'Dockside Crew', 'desc' => 'Run the piers.', 'badge' => '', 'owner_id' => $boss->id, 'created' => 1700000000, 'is_gang' => '1', 'gang_level' => 2, 'gang_xp' => 40],
        ['id' => 12, 'name' => 'Hilltop Kings', 'desc' => '', 'badge' => 'b0503Xs', 'owner_id' => 0, 'created' => 1700000000, 'is_gang' => '1', 'gang_level' => 5, 'gang_xp' => 10],
        ['id' => 13, 'name' => 'Just a group', 'desc' => '', 'badge' => '', 'owner_id' => $boss->id, 'created' => 1700000000, 'is_gang' => '0', 'gang_level' => 1, 'gang_xp' => 0],
    ]);

    DB::table('rp_gang_roles')->insert([
        ['id' => 1, 'gang_id' => 11, 'name' => 'Boss', 'sort_order' => 1],
        ['id' => 2, 'gang_id' => 11, 'name' => 'Runner', 'sort_order' => 0],
    ]);

    DB::table('rp_gang_members')->insert([
        ['gang_id' => 11, 'user_id' => $boss->id, 'role_id' => 1, 'joined_at' => 1700000000],
        ['gang_id' => 11, 'user_id' => $me->id, 'role_id' => 2, 'joined_at' => 1700000500],
    ]);

    $room = DB::table('rooms')->insertGetId(['name' => 'not this one', 'caption' => 'Pier 39']);

    DB::table('rp_turfs')->insert(['room_id' => $room, 'gang_id' => 11, 'claimed_by' => $boss->id, 'claimed_at' => 1700001000]);

    return ['me' => $me, 'boss' => $boss];
}

test('the gangs page ranks the gangs and shows the block they hold', function () {
    ['me' => $me] = foundGangs();

    $this->actingAs($me)
        ->get(route('gangs.index'))
        ->assertOk()
        ->assertSeeInOrder(['Dockside Crew', 'Hilltop Kings'])
        ->assertDontSee('Just a group')
        ->assertSee('Pier 39')
        ->assertSee('You are Runner');
});

test('the gangs page renders for a player outside every gang', function () {
    foundGangs();

    $this->actingAs(User::factory()->create())
        ->get(route('gangs.index'))
        ->assertOk()
        ->assertSee('Not in a gang');
});

test('the home page shows the gang the player belongs to', function () {
    ['me' => $me] = foundGangs();

    $this->actingAs($me)
        ->get(route('me.show'))
        ->assertOk()
        ->assertSee('Dockside Crew');
});
