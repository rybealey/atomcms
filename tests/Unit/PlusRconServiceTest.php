<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\PlusRconService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\CreatesApplication;

/**
 * Adapted from the reference test in the Task 6 brief to the real
 * App\Contracts\Rcon signatures:
 *  - RconResponse::successful() is status===0 (0 = OK), not status===1.
 *  - The typed helpers (alertUser, forwardUser, ...) return void and take
 *    an App\Models\User, not a bare id, so "unsupported command" and
 *    "alert wire format" are exercised through the low-level
 *    sendPlusCommand()/sendCommand() entry points that do return a
 *    RconResponse.
 *
 * Boots the app container directly (CreatesApplication) rather than
 * extending Tests\TestCase: PlusRconService needs real facades (Log) and
 * config, but these three cases never touch the database, so pulling in
 * Tests\TestCase's RefreshDatabase/migrate-fresh/seed stack would be pure
 * overhead - and this environment's dev database isn't set up as a
 * throwaway migration target.
 */
class PlusRconServiceTest extends BaseTestCase
{
    use CreatesApplication;

    private $server;

    private int $port;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($this->server, false);
        $this->port = (int) substr($name, strrpos($name, ':') + 1);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            fclose($this->server);
        }

        parent::tearDown();
    }

    private function receiveOne(): string
    {
        $conn = stream_socket_accept($this->server, 2);
        $data = stream_get_contents($conn);
        fclose($conn);

        return $data;
    }

    public function test_give_credits_wire_format(): void
    {
        $rcon = new PlusRconService('127.0.0.1', $this->port);
        $response = $rcon->sendPlusCommand('give_user_currency', [42, 'credits', 100]);

        $this->assertSame("give_user_currency\x01".'42:credits:100', $this->receiveOne());
        $this->assertTrue($response->successful());
    }

    public function test_unsupported_command_returns_failure_without_connecting(): void
    {
        // Nothing listens on port 1, so if PlusRconService tried to connect
        // for an unsupported command this would fail loudly instead of
        // silently degrading.
        $rcon = new PlusRconService('127.0.0.1', 1);

        $response = $rcon->sendCommand('forwarduser', ['user_id' => 42, 'room_id' => 7]);

        $this->assertFalse($response->successful());
    }

    public function test_alert_strips_colons_from_message(): void
    {
        $rcon = new PlusRconService('127.0.0.1', $this->port);
        $user = new User;
        $user->id = 42;

        $rcon->alertUser($user, 'note: hello');

        $this->assertSame("alert_user\x01".'42:note; hello', $this->receiveOne());
    }
}
