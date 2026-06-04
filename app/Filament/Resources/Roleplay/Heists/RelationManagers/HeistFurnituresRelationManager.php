<?php

namespace App\Filament\Resources\Roleplay\Heists\RelationManagers;

use App\Models\Roleplay\HeistFurniture;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Furnitures attached to a heist, each with a role:
 *   - Keypad  -> the access gate (opens the keypad window; codes managed in the
 *     Keypads tab).
 *   - Search  -> stand-and-search loot.
 *   - Pickup  -> grab-and-go loot.
 *
 * A furni base belongs to exactly one heist (item_base_id is unique across the
 * table), so the furniture picker rejects a base already used elsewhere.
 *
 * Foundation status: only the Keypad role drives behaviour today (the gate).
 * Search / Pickup loot mechanics are future work.
 */
class HeistFurnituresRelationManager extends RelationManager
{
    protected static string $relationship = 'furnitures';

    protected static ?string $title = 'Furnitures';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_base_id')
                    ->label('Furniture')
                    ->relationship(
                        name: 'itemBase',
                        titleAttribute: 'item_name',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('item_name'),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'That furniture is already attached to a heist.',
                    ])
                    ->helperText('items_base row to attach. Each furni base can belong to only one heist.')
                    ->columnSpanFull(),

                Select::make('role')
                    ->label('Role')
                    ->options(HeistFurniture::ROLE_OPTIONS)
                    ->required()
                    ->default(HeistFurniture::ROLE_KEYPAD)
                    ->helperText('Keypad = access gate. Search / Pickup = loot (behaviour is future work).')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item_base_id')
            ->columns([
                ImageColumn::make('icon')
                    ->getStateUsing(fn ($record) => url($record->itemBase?->icon()))
                    ->size('25px')
                    ->label('Icon')
                    ->circular(),

                TextColumn::make('itemBase.item_name')
                    ->label('Furniture')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('role')
                    ->label('Role')
                    ->formatStateUsing(fn (string $state) => HeistFurniture::ROLE_OPTIONS[$state] ?? $state)
                    ->badge()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
