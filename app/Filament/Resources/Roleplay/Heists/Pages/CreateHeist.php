<?php

namespace App\Filament\Resources\Roleplay\Heists\Pages;

use App\Filament\Resources\Roleplay\Heists\HeistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHeist extends CreateRecord
{
    protected static string $resource = HeistResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
