<?php

namespace Tests\Feature\Plus;

use App\Contracts\Rcon;
use App\Models\User;
use App\Models\WebsiteHousekeepingPermission;
use App\Services\FakeRcon;
use App\Services\HousekeepingPermissionsService;
use App\Support\Housekeeping\GameAbilities;
use App\Support\Housekeeping\Logs\LogSources;
use App\Support\Housekeeping\Logs\LogStream;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/**
 * The housekeeping panel's JSON API against a real PlusEMU database: the
 * emulator's dump with its SQL updates applied (the roleplay tables) and the
 * CMS migrations - the schema beta and prod run. Run through phpunit.plus.xml
 * like the rest of tests/Feature/Plus; every test is a rolled-back
 * transaction, so it creates its own rows and leaves the database as it was.
 *
 * Skipped where the roleplay tables are missing: a pixelrp_test made from the
 * dump alone, without the updates, has no rp_* tables to read.
 */
class HousekeepingApiTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private FakeRcon $rcon;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('rp_jail') || ! Schema::hasTable('website_staff_notes')) {
            $this->markTestSkipped('Needs the PlusEMU schema with the emulator SQL updates and the CMS migrations.');
        }

        $this->withoutVite();
        Cache::flush();
        $this->rcon = new FakeRcon(true);
        $this->app->instance(Rcon::class, $this->rcon);
        DB::table('website_installation')->updateOrInsert(['installation_key' => 'housekeeping-test'], ['completed' => true, 'step' => 5]);
    }

    /**
     * A staff member at the top rank, every capability and in-game ability
     * open to them.
     */
    private function staff(): User
    {
        $top = (int) DB::table('ranks')->max('id');

        foreach (['can_access_housekeeping', 'manage_bans', 'edit_user', 'manage_support', 'manage_room_chatlogs', 'manage_private_chatlogs', 'view_activity_logs', 'view_economy', 'view_payments', 'manage_housekeeping_permissions', 'manage_website_settings', 'manage_emulator_settings'] as $permission) {
            WebsiteHousekeepingPermission::query()->updateOrCreate(['permission' => $permission], ['min_rank' => $top, 'description' => 'test']);
        }

        foreach (array_keys(GameAbilities::MIRRORED) as $command) {
            DB::table('permissions_commands')->updateOrInsert(['command' => $command], ['group_id' => $top, 'subscription_id' => 0]);
        }

        HousekeepingPermissionsService::clearCache();
        GameAbilities::clearCache();

        return $this->user('HkStaff', ['rank' => $top]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(string $name, array $attributes = []): User
    {
        $id = DB::table('users')->insertGetId($attributes + [
            'username' => $name . random_int(1000, 9999),
            'password' => bcrypt('secret'),
            'mail' => strtolower($name) . random_int(1000, 9999) . '@example.test',
            'rank' => 1,
            'look' => 'hr-100-61.hd-180-1.ch-210-66.lg-270-110.sh-305-62',
            'gender' => 'M',
            'motto' => '',
            'account_created' => (string) (time() - 86400),
            'last_online' => time() - 600,
            'online' => 0,
            'credits' => 100,
            'ip_last' => '203.0.113.9',
            'ip_reg' => '203.0.113.9',
            'auth_ticket' => '',
        ]);

        return User::query()->findOrFail($id);
    }

    public function test_every_screen_answers_for_staff(): void
    {
        $staff = $this->staff();
        $player = $this->user('Suspect');
        DB::table('rp_coin_ledger')->insert(['user_id' => $player->id, 'username' => $player->username, 'amount' => 50, 'balance_after' => 150, 'source' => 'Wage - SFPD', 'created_at' => time() - 60]);
        DB::table('rp_jail')->insert(['user_id' => $player->id, 'officer_id' => $staff->id, 'room_id' => 1, 'sentence_seconds' => 300, 'seconds_left' => 200, 'jailed_at' => time() - 100, 'released_at' => 0, 'trial_requested_at' => 0, 'trial_result' => 0, 'charges' => "1\tAssault"]);
        DB::table('chatlogs')->insert(['user_id' => $player->id, 'room_id' => 1, 'message' => 'hello city', 'timestamp' => time() - 30]);

        $paths = [
            '/me', '/me/pulse', '/today', '/search?q=Sus',
            '/players', '/players/filters', "/players/{$player->id}", "/players/{$player->id}/card", "/players/{$player->id}/peek",
            "/players/{$player->id}/timeline", "/players/{$player->id}/money", "/players/{$player->id}/justice", "/players/{$player->id}/chat",
            "/players/{$player->id}/chat/transcript?room=1", "/players/{$player->id}/security", "/players/{$player->id}/inventory", "/players/{$player->id}/discipline",
            '/discipline', '/security', '/chatlogs?q=hello',
            '/justice', '/corporations', '/gangs', '/economy', '/rooms',
            '/logs', '/logs/counts', '/logs/counts?meta=1', '/views', '/audit',
            '/access', '/staff', '/settings', '/settings/changes', '/emulator-settings',
        ];

        foreach ($paths as $path) {
            $this->actingAs($staff)->getJson('/housekeeping/api' . $path)->assertOk();
        }

        $this->actingAs($staff)->getJson("/housekeeping/api/players/{$player->id}/timeline?range=all")
            ->assertOk()
            ->assertJsonPath('rows.0.source', 'chatlogs');
    }

    public function test_the_log_stream_pages_without_repeating_or_skipping_rows(): void
    {
        $player = $this->user('Ledger');
        $other = $this->user('Payee');
        $at = time() - 120;

        // Rows that share one second across two sources: the cursor's tie-breaks.
        for ($i = 0; $i < 7; $i++) {
            DB::table('rp_coin_ledger')->insert(['user_id' => $player->id, 'username' => $player->username, 'amount' => $i + 1, 'balance_after' => 100, 'source' => 'Test', 'created_at' => $at]);
        }

        for ($i = 0; $i < 5; $i++) {
            DB::table('rp_pay_transfers')->insert(['sender_id' => $player->id, 'recipient_id' => $other->id, 'amount' => 10, 'note' => '', 'created_at' => $at, 'delivered' => 1]);
        }

        $sources = array_intersect_key(LogSources::all(), array_flip(['rp_coin_ledger', 'rp_pay_transfers']));
        $seen = [];
        $cursor = null;

        do {
            $page = LogStream::page($sources, ['player' => $player->id], $cursor, 3);

            foreach ($page['rows'] as $row) {
                $seen[] = $row['key'];
            }

            $cursor = LogStream::decode($page['next']);
        } while ($cursor !== null);

        $this->assertCount(12, $seen);
        $this->assertCount(12, array_unique($seen));
    }

    public function test_a_ban_covers_every_character_and_lifting_it_keeps_the_history(): void
    {
        $staff = $this->staff();
        $root = $this->user('Main');
        $alt = $this->user('Alt', ['parent_id' => $root->id]);

        $this->actingAs($staff)->postJson("/housekeeping/api/players/{$alt->id}/ban", ['seconds' => 3600, 'reason' => 'Dodging jail'])->assertOk();

        $this->assertSame(2, DB::table('bans')->where('bantype', 'user')->whereIn('value', [$root->username, $alt->username])->where('expire', '>', time())->count());
        $this->assertTrue(collect($this->rcon->calls)->contains(fn (array $c): bool => ($c['args']['command'] ?? null) === 'reloadbans'));

        $this->actingAs($staff)->postJson("/housekeeping/api/players/{$root->id}/unban", ['reason' => 'Appeal accepted'])->assertOk();

        $this->assertSame(0, DB::table('bans')->whereIn('value', [$root->username, $alt->username])->where('expire', '>', time())->count());
        $this->assertSame(2, DB::table('bans')->whereIn('value', [$root->username, $alt->username])->count());
        $this->assertSame(2, DB::table('activity_log')->where('log_name', 'housekeeping')->whereIn('event', ['ban', 'unban'])->where('causer_id', $staff->id)->count());
    }

    public function test_coins_for_an_offline_player_are_written_to_their_account_and_ledger(): void
    {
        $staff = $this->staff();
        $player = $this->user('Offline', ['credits' => 100, 'online' => 0]);

        $this->actingAs($staff)->postJson("/housekeeping/api/players/{$player->id}/coins", ['amount' => 250, 'reason' => 'Stuck ATM'])
            ->assertOk()
            ->assertJsonPath('path', 'saved');

        $this->assertSame(350, (int) DB::table('users')->where('id', $player->id)->value('credits'));
        $this->assertSame(350, (int) DB::table('rp_coin_ledger')->where('user_id', $player->id)->value('balance_after'));
        $this->assertSame('Stuck ATM', DB::table('activity_log')->where('event', 'coins')->where('subject_id', (string) $player->id)->value(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.reason'))")));
    }

    public function test_coins_for_an_online_player_go_through_rcon(): void
    {
        $staff = $this->staff();
        $player = $this->user('Online', ['credits' => 100, 'online' => 1]);

        $this->actingAs($staff)->postJson("/housekeeping/api/players/{$player->id}/coins", ['amount' => -40, 'reason' => 'Refund reversed'])
            ->assertOk()
            ->assertJsonPath('path', 'live');

        $this->assertTrue(collect($this->rcon->calls)->contains(fn (array $c): bool => ($c['args']['command'] ?? null) === 'takecredits' && (int) $c['args']['data']['credits'] === 40));
        $this->assertSame(100, (int) DB::table('users')->where('id', $player->id)->value('credits'));
    }

    public function test_an_online_player_cannot_be_muted_from_the_web(): void
    {
        $staff = $this->staff();
        $player = $this->user('Chatty', ['online' => 1]);

        $this->actingAs($staff)->postJson("/housekeeping/api/players/{$player->id}/mute", ['seconds' => 600, 'reason' => 'Spam'])->assertStatus(422);

        DB::table('users')->where('id', $player->id)->update(['online' => 0]);

        $this->actingAs($staff)->postJson("/housekeeping/api/players/{$player->id}/mute", ['seconds' => 600, 'reason' => 'Spam'])->assertOk();
        $this->assertSame(600, (int) DB::table('users')->where('id', $player->id)->value('time_muted'));
    }

    public function test_the_crime_catalogue_follows_the_in_game_justice_rank(): void
    {
        $staff = $this->staff();
        $crime = ['key_name' => 'testcrime' . random_int(10, 99), 'name' => 'Test crime', 'jail_seconds' => 60, 'severity' => 2, 'stackable' => true, 'ticketable' => true, 'ticket_amount' => 25, 'active' => true];

        DB::table('permissions_commands')->where('command', 'rp_city_justice')->update(['group_id' => (int) $staff->rank + 1]);
        GameAbilities::clearCache();
        $this->actingAs($staff)->postJson('/housekeeping/api/justice/crimes', $crime)->assertForbidden();

        DB::table('permissions_commands')->where('command', 'rp_city_justice')->update(['group_id' => (int) $staff->rank]);
        GameAbilities::clearCache();
        $this->actingAs($staff)->postJson('/housekeeping/api/justice/crimes', $crime)->assertCreated();
        $this->assertTrue(DB::table('rp_crimes')->where('key_name', $crime['key_name'])->exists());
    }

    public function test_access_will_not_lock_its_editor_out(): void
    {
        $staff = $this->staff();
        $below = (int) $staff->rank - 1;
        DB::table('users')->where('id', $staff->id)->update(['rank' => $below]);
        WebsiteHousekeepingPermission::query()->whereIn('permission', ['can_access_housekeeping', 'manage_housekeeping_permissions'])->update(['min_rank' => $below]);
        HousekeepingPermissionsService::clearCache();
        $staff->refresh();

        // Raising Access above your own rank would lock you out of it.
        $this->actingAs($staff)->putJson('/housekeeping/api/access', ['screens' => ['manage_housekeeping_permissions' => $below + 1], 'reason' => 'Testing'])->assertForbidden();
        $this->assertSame($below, (int) WebsiteHousekeepingPermission::query()->where('permission', 'manage_housekeeping_permissions')->value('min_rank'));

        DB::table('permissions_commands')->where('command', 'rp_city_world')->update(['group_id' => $below]);
        GameAbilities::clearCache();
        $this->actingAs($staff)->putJson('/housekeeping/api/access', ['commands' => ['rp_city_world' => 2], 'reason' => 'Builders steer the weather'])->assertOk();
        $this->assertSame(2, (int) DB::table('permissions_commands')->where('command', 'rp_city_world')->value('group_id'));
        $this->assertTrue(collect($this->rcon->calls)->contains(fn (array $c): bool => ($c['args']['command'] ?? null) === 'reloadranks'));
    }

    public function test_staff_notes_belong_to_the_account(): void
    {
        $staff = $this->staff();
        $root = $this->user('Root');
        $alt = $this->user('Second', ['parent_id' => $root->id]);

        $this->actingAs($staff)->postJson("/housekeeping/api/players/{$alt->id}/notes", ['body' => 'Warned for spawn camping'])->assertOk();

        $this->actingAs($staff)->getJson("/housekeeping/api/players/{$root->id}/discipline")
            ->assertOk()
            ->assertJsonPath('notes.0.body', 'Warned for spawn camping')
            ->assertJsonPath('notes.0.character', $alt->username);
    }
}
