<?php

namespace App\Filament\Resources\Roleplay\ChargeTypes\Pages;

use App\Filament\Resources\Roleplay\ChargeTypes\ChargeTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChargeType extends CreateRecord
{
    protected static string $resource = ChargeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
