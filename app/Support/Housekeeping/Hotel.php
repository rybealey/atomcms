<?php

namespace App\Support\Housekeeping;

use App\Contracts\Rcon;
use App\Exceptions\RconConnectionException;
use Throwable;

/**
 * Live actions and reloads from the panel. Unlike the typed helpers on the
 * Rcon contract, which log a failure and carry on, these return what the
 * socket did so the panel can say so and the audit row can keep it.
 */
final class Hotel
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function send(string $command, array $data = []): HotelResult
    {
        try {
            $response = app(Rcon::class)->sendCommand($command, $data);

            return new HotelResult($command, $response->successful(), $response->message);
        } catch (RconConnectionException $exception) {
            return new HotelResult($command, false, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return new HotelResult($command, false, 'The emulator could not be reached');
        }
    }

    public static function alert(int $userId, string $message): HotelResult
    {
        return self::send('alertuser', ['user_id' => $userId, 'message' => $message]);
    }

    public static function disconnect(int $userId): HotelResult
    {
        return self::send('disconnect', ['user_id' => $userId]);
    }

    /**
     * Coins are the hotel's credits. A negative amount takes them.
     */
    public static function adjustCoins(int $userId, int $amount): HotelResult
    {
        return $amount >= 0
            ? self::send('givecredits', ['user_id' => $userId, 'credits' => $amount])
            : self::send('takecredits', ['user_id' => $userId, 'credits' => -$amount]);
    }

    public static function giveBadge(int $userId, string $badge): HotelResult
    {
        return self::send('givebadge', ['user_id' => $userId, 'badge' => $badge]);
    }

    public static function takeBadge(int $userId, string $badge): HotelResult
    {
        return self::send('takebadge', ['user_id' => $userId, 'badge' => $badge]);
    }

    /**
     * Ask the emulator to re-read a cache it keeps (bans, filter, catalog,
     * items, navigator, ranks, server settings), so a Saved change behaves as
     * Live.
     */
    public static function reload(string $what): HotelResult
    {
        return self::send('reload' . $what);
    }

    public static function online(): bool
    {
        try {
            return app(Rcon::class)->isConnected();
        } catch (Throwable) {
            return false;
        }
    }
}
