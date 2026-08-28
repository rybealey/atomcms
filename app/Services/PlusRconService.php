<?php

namespace App\Services;

use App\Contracts\Rcon;
use App\Data\RconResponse;
use App\Emulator\Contracts\FurnitureRepository;
use App\Enums\CurrencyTypes;
use App\Exceptions\RconConnectionException;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Bridge to a PlusEMU emulator over its RCON socket.
 *
 * Unlike Arcturus, PlusEMU speaks a fire-and-forget wire protocol: one
 * command per TCP connection, formatted `command \x01 param1:param2:...`,
 * and it never writes a response back. Every "successful" RconResponse
 * here is therefore synthesized from a successful socket write, not a
 * parsed reply. Commands PlusEMU has no equivalent for return a failure
 * response WITHOUT opening a socket, so the CMS's existing DB-fallback
 * paths (see FurnitureRepository, SendCurrency, etc.) kick in.
 *
 * `sendGift()` is a special case of that fallback: callers like
 * SendFurniture gate on isConnected() (a pure reachability check) before
 * calling it, so a silent "unsupported" no-op here would drop the gift
 * entirely - isConnected() would say true while sendGift() did nothing.
 * Since there's no PlusEMU wire command for it, sendGift() always grants
 * the item directly through FurnitureRepository (the same DB-write-first
 * pattern setMotto/setRank use), instead of routing through
 * sendCommand()/unsupported(). See individual method docs below for why
 * forwardUser()/updateConfig() are correct as a logged no-op instead.
 */
class PlusRconService implements Rcon
{
    public function __construct(
        private ?string $host = null,
        private ?int $port = null,
        private ?FurnitureRepository $furniture = null,
    ) {}

    private function furniture(): FurnitureRepository
    {
        return $this->furniture ??= app(FurnitureRepository::class);
    }

    public function isConnected(): bool
    {
        try {
            $socket = $this->connect();
            fclose($socket);

            return true;
        } catch (RconConnectionException) {
            return false;
        }
    }

    /**
     * Translate an Arcturus-shaped RCON command key + payload (the same
     * keys RconService::dispatchCommand uses) into the PlusEMU wire
     * protocol. Keys with no PlusEMU equivalent degrade to a failure
     * response without attempting a connection.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function sendCommand(string $command, ?array $data = null): RconResponse
    {
        $data ??= [];

        return match ($command) {
            'givecredits' => $this->sendPlusCommand('give_user_currency', [
                $data['user_id'], 'credits', $data['credits'],
            ]),
            'givepoints' => $this->translateGivePoints($data),
            'givebadge' => $this->sendPlusCommand('give_user_badge', [
                $data['user_id'], $data['badge'],
            ]),
            'setmotto' => $this->translateSetMotto($data),
            'setrank' => $this->translateSetRank($data),
            'disconnect' => $this->sendPlusCommand('disconnect_user', [$data['user_id']]),
            'alertuser' => $this->sendPlusCommand('alert_user', [
                $data['user_id'], $this->sanitize((string) $data['message']),
            ]),
            'updatewordfilter' => $this->sendPlusCommand('reload_filter'),
            'updatecatalog' => $this->sendPlusCommand('reload_catalog'),
            'givepassive' => $this->sendPlusCommand('give_user_passive', [
                $data['user_id'], $data['seconds'],
            ]),
            // forwarduser, executecommand, and any unknown key: PlusEMU has
            // no wire command for these, and there's no fallback state to
            // write, so a logged no-op is correct (see forwardUser()'s and
            // updateConfig()'s docblocks). 'sendgift' is never routed here -
            // see sendGift()'s docblock for why it grants directly instead.
            default => $this->unsupported($command),
        };
    }

    /**
     * Low-level send: one PlusEMU command per TCP connection, no response
     * bytes ever read back. Public so callers/tests that already know the
     * exact wire command can bypass the Arcturus-key translation above.
     *
     * @param  array<int, int|string>  $params
     */
    public function sendPlusCommand(string $command, array $params = []): RconResponse
    {
        $payload = $command . "\x01" . implode(':', $params);

        $socket = $this->connect();

        try {
            $this->write($socket, $payload);
        } finally {
            fclose($socket);
        }

        return new RconResponse(status: 0, message: 'ok');
    }

    /**
     * PlusEMU has no RCON command for gifting an item into inventory, so this
     * is not routed through sendCommand()/unsupported() like the rest of the
     * "no wire equivalent" commands. Callers (SendFurniture) check
     * isConnected() first and expect sendGift() to actually deliver the item
     * when that check passes - a logged no-op here would silently drop the
     * gift instead of degrading to the database path. Grant it directly.
     */
    public function sendGift(User $user, int $itemId, string $message = 'Here is a gift.'): void
    {
        Log::info("PlusRconService: 'sendgift' has no PlusEMU RCON equivalent; granting via FurnitureRepository instead", [
            'user_id' => $user->id,
            'item_id' => $itemId,
        ]);

        $this->furniture()->grant($user, $itemId, 1);
    }

    public function giveCurrency(User $user, CurrencyTypes $currency, int $amount): void
    {
        if ($currency === CurrencyTypes::Credits) {
            $this->dispatchCommand('givecredits', [
                'user_id' => $user->id,
                'credits' => $amount,
            ]);

            return;
        }

        $this->dispatchCommand('givepoints', [
            'user_id' => $user->id,
            'points' => $amount,
            'type' => $currency,
        ]);
    }

    public function giveBadge(User $user, string $badge): void
    {
        $this->dispatchCommand('givebadge', [
            'user_id' => $user->id,
            'badge' => $badge,
        ]);
    }

    public function setMotto(User $user, string $motto): void
    {
        $this->dispatchCommand('setmotto', [
            'user_id' => $user->id,
            'motto' => $motto,
        ]);
    }

    public function updateWordFilter(): void
    {
        $this->dispatchCommand('updatewordfilter');
    }

    public function disconnectUser(User $user): void
    {
        $this->dispatchCommand('disconnect', [
            'user_id' => $user->id,
            'username' => $user->username,
        ]);
    }

    public function setRank(User $user, int $rank): void
    {
        $this->dispatchCommand('setrank', [
            'user_id' => $user->id,
            'rank' => $rank,
        ]);
    }

    public function updateCatalog(): void
    {
        $this->dispatchCommand('updatecatalog');
    }

    public function alertUser(User $user, string $message): void
    {
        $this->dispatchCommand('alertuser', [
            'user_id' => $user->id,
            'message' => $message,
        ]);
    }

    public function grantPassive(User $user, int $seconds): void
    {
        $this->dispatchCommand('givepassive', [
            'user_id' => $user->id,
            'seconds' => $seconds,
        ]);
    }

    /**
     * Unlike sendGift, this is correct as a logged no-op: forwarding a
     * player's live client to a room is meaningless when nobody is
     * connected to forward, and there's no persisted state to write as a
     * fallback (there's nothing analogous to a FurnitureRepository row for
     * "the room the user is standing in"). No PlusEMU RCON equivalent
     * exists either way, so this always degrades via unsupported().
     */
    public function forwardUser(User $user, int $roomId): void
    {
        $this->dispatchCommand('forwarduser', [
            'user_id' => $user->id,
            'room_id' => $roomId,
        ]);
    }

    /**
     * Same reasoning as forwardUser(): this executes an arbitrary staff
     * command against a live session. There's no data to lose and nothing
     * to write to the database as a fallback if the command can't reach a
     * connected client, so a logged no-op is the correct degrade here too.
     */
    public function updateConfig(User $user, string $command): void
    {
        $this->dispatchCommand('executecommand', [
            'user_id' => $user->id,
            'command' => $command,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function translateGivePoints(array $data): RconResponse
    {
        $currency = $data['type'] ?? null;

        $name = $currency instanceof CurrencyTypes ? match ($currency) {
            CurrencyTypes::Duckets => 'duckets',
            CurrencyTypes::Diamonds => 'diamonds',
            CurrencyTypes::Points => 'gotw',
            default => null,
        } : null;

        if ($name === null) {
            return $this->unsupported("givepoints(type={$currency?->name})");
        }

        return $this->sendPlusCommand('give_user_currency', [$data['user_id'], $name, $data['points']]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function translateSetMotto(array $data): RconResponse
    {
        User::whereKey($data['user_id'])->update(['motto' => $data['motto']]);

        return $this->sendPlusCommand('reload_user_motto', [$data['user_id']]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function translateSetRank(array $data): RconResponse
    {
        User::whereKey($data['user_id'])->update(['rank' => $data['rank']]);

        return $this->sendPlusCommand('reload_user_rank', [$data['user_id']]);
    }

    private function unsupported(string $command): RconResponse
    {
        Log::info("PlusRconService: '{$command}' has no PlusEMU equivalent; degrading", [
            'command' => $command,
        ]);

        return new RconResponse(status: 1, message: "'{$command}' unsupported by PlusEMU");
    }

    private function sanitize(string $message): string
    {
        // PlusEMU splits alert_user params on ':', so it can't appear in the message.
        return str_replace(':', ';', $message);
    }

    /**
     * Typed RCON helpers are fire-and-forget operations. Preserve that
     * contract while retaining transport and emulator failures in the
     * application log, mirroring RconService::dispatchCommand.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function dispatchCommand(string $command, ?array $data = null): void
    {
        try {
            $response = $this->sendCommand($command, $data);

            if (! $response->successful()) {
                Log::warning('RCON command was rejected by the emulator', [
                    'command' => $command,
                    'status' => $response->status,
                    'message' => $response->message,
                ]);
            }
        } catch (RconConnectionException $exception) {
            Log::error('RCON command failed', [
                'command' => $command,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * PlusEMU accepts one command per TCP connection and never writes a
     * reply, but the connection itself still needs to succeed (and the
     * source IP still needs to be in AllowedAddresses).
     *
     * @return resource
     */
    private function connect()
    {
        $errorCode = 0;
        $errorMessage = '';
        $timeout = max(0.1, (float) config('habbo.rcon.connect_timeout_seconds', 1));

        $socket = @stream_socket_client(
            $this->endpoint(),
            $errorCode,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
        );

        if ($socket === false) {
            throw new RconConnectionException("Unable to connect to RCON: {$errorMessage} ({$errorCode})");
        }

        $readTimeout = max(0.1, (float) config('habbo.rcon.read_timeout_seconds', 2));
        $seconds = (int) $readTimeout;
        $microseconds = (int) (($readTimeout - $seconds) * 1_000_000);
        stream_set_timeout($socket, $seconds, $microseconds);

        return $socket;
    }

    private function endpoint(): string
    {
        $host = trim($this->host ?? (string) setting('rcon_ip'));
        $port = $this->port ?? (int) setting('rcon_port');

        if ($host === '' || $port < 1 || $port > 65535) {
            throw new RconConnectionException('RCON host or port is not configured correctly');
        }

        $formattedHost = str_contains($host, ':') && ! str_starts_with($host, '[')
            ? "[{$host}]"
            : $host;

        return "tcp://{$formattedHost}:{$port}";
    }

    /**
     * @param  resource  $socket
     */
    private function write($socket, string $payload): void
    {
        $written = 0;
        $length = strlen($payload);

        while ($written < $length) {
            $bytes = fwrite($socket, substr($payload, $written));

            if ($bytes === false || $bytes === 0) {
                throw new RconConnectionException('RCON connection closed before the command was fully written');
            }

            $written += $bytes;
        }
    }
}
