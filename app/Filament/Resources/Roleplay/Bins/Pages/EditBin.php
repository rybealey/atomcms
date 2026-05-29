<?php

namespace App\Filament\Resources\Roleplay\Bins\Pages;

use App\Filament\Resources\Roleplay\Bins\BinResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBin extends EditRecord
{
    protected static string $resource = BinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
