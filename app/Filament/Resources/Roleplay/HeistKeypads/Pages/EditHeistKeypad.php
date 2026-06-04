<?php

namespace App\Filament\Resources\Roleplay\HeistKeypads\Pages;

use App\Filament\Resources\Roleplay\HeistKeypads\HeistKeypadResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHeistKeypad extends EditRecord
{
    protected static string $resource = HeistKeypadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
