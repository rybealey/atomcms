<?php

namespace App\Support\Housekeeping;

/**
 * What happened when the panel asked the emulator to do something: sent, or
 * not and why. PlusEMU never answers an RCON write, so "sent" is the most the
 * socket can promise.
 */
final readonly class HotelResult
{
    public function __construct(
        public string $command,
        public bool $sent,
        public string $message,
    ) {}

    /**
     * @return array{command: string, sent: bool, message: string}
     */
    public function toArray(): array
    {
        return ['command' => $this->command, 'sent' => $this->sent, 'message' => $this->message];
    }
}
