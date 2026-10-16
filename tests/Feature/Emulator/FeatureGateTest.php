<?php

use App\Emulator\Data\Feature;
use App\Emulator\Emulator;

test('feature support follows the configured driver', function () {
    config(['emulator.driver' => 'arcturus']);

    expect(Emulator::supports(Feature::RareValues))->toBeTrue()
        ->and(Emulator::supports(Feature::RoomChatlogs))->toBeTrue();

    config(['emulator.driver' => 'plus']);

    expect(Emulator::supports(Feature::RareValues))->toBeFalse()
        ->and(Emulator::supports(Feature::RoomChatlogs))->toBeTrue()
        ->and(Emulator::supports(Feature::BanManagement))->toBeTrue()
        ->and(Emulator::supports(Feature::Wordfilter))->toBeFalse();
});
