<?php

namespace App\Filament\Resources\Roleplay\Bins\Pages;

use App\Filament\Resources\Roleplay\Bins\BinResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBin extends CreateRecord
{
    protected static string $resource = BinResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
