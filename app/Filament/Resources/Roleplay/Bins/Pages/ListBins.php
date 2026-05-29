<?php

namespace App\Filament\Resources\Roleplay\Bins\Pages;

use App\Filament\Resources\Roleplay\Bins\BinResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBins extends ListRecords
{
    protected static string $resource = BinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
