<?php

namespace App\Filament\Resources\Roleplay\ChargeTypes\Pages;

use App\Filament\Resources\Roleplay\ChargeTypes\ChargeTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChargeType extends EditRecord
{
    protected static string $resource = ChargeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => ! $this->record->is_system),
        ];
    }
}
