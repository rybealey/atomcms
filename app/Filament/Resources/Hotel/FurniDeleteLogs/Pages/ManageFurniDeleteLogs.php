<?php

namespace App\Filament\Resources\Hotel\FurniDeleteLogs\Pages;

use App\Filament\Resources\Hotel\FurniDeleteLogs\FurniDeleteLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageFurniDeleteLogs extends ManageRecords
{
    protected static string $resource = FurniDeleteLogResource::class;

    protected function getActions(): array
    {
        return [];
    }
}
