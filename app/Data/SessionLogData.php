<?php

namespace App\Data;

use Carbon\Carbon;

final readonly class SessionLogData
{
    /**
     * @param  array{is_desktop: bool, platform: string|bool, browser: string|bool}  $agent
     */
    public function __construct(
        public array $agent,
        public ?string $ipAddress,
        public bool $isCurrentDevice,
        public string $lastActive,
        public string $id = '',
        public ?Carbon $lastActiveAt = null,
    ) {}

    /** "Safari on macOS": what the sessions page prints for a device. */
    public function device(): string
    {
        $browser = is_string($this->agent['browser']) && $this->agent['browser'] !== '' ? $this->agent['browser'] : __('Browser');
        $platform = is_string($this->agent['platform']) && $this->agent['platform'] !== '' ? $this->agent['platform'] : __('unknown device');

        return __(':browser on :platform', ['browser' => $browser, 'platform' => $platform]);
    }
}
