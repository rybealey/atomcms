<?php

namespace App\Filament\Resources\Roleplay\HeistKeypads\Pages;

use App\Filament\Resources\Roleplay\HeistKeypads\HeistKeypadResource;
use Filament\Resources\Pages\ListRecords;

class ListHeistKeypads extends ListRecords
{
    protected static string $resource = HeistKeypadResource::class;

    // No CreateAction: keypad rows are created by gameplay, not staff.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
