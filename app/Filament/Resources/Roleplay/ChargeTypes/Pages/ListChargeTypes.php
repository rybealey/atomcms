<?php

namespace App\Filament\Resources\Roleplay\ChargeTypes\Pages;

use App\Filament\Resources\Roleplay\ChargeTypes\ChargeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChargeTypes extends ListRecords
{
    protected static string $resource = ChargeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
