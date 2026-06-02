<?php

namespace App\Filament\Resources\Roleplay\Heists\Pages;

use App\Filament\Resources\Roleplay\Heists\HeistResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHeist extends EditRecord
{
    protected static string $resource = HeistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
