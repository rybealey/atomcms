<?php

namespace App\Filament\Resources\Roleplay\Heists\Pages;

use App\Filament\Resources\Roleplay\Heists\HeistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHeists extends ListRecords
{
    protected static string $resource = HeistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
