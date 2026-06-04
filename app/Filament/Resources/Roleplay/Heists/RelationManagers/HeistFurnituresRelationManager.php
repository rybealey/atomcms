<?php

namespace App\Filament\Resources\Roleplay\Heists\RelationManagers;

use App\Models\Roleplay\HeistFurniture;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Furnitures attached to a heist, each with a role:
 *   - Keypad -> the access gate. Keyed by a specific PLACED furni (its
 *     items.id) and carrying that keypad's access code. The emulator re-rolls
 *     the code on every in-game open; the value here is the current / override.
 *   - Search -> stand-and-search loot. Keyed by furniture type (item base).
 *   - Pickup -> grab-and-go loot. Keyed by furniture type (item base).
 *
 * A placed keypad is unique; a loot furni base belongs to exactly one heist.
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
        $isKeypad = fn (callable $get): bool => $get('role') === HeistFurniture::ROLE_KEYPAD;
        $isLoot = fn (callable $get): bool => $get('role') !== HeistFurniture::ROLE_KEYPAD;

        return $schema
            ->components([
                Select::make('role')
                    ->label('Role')
                    ->options(HeistFurniture::ROLE_OPTIONS)
                    ->required()
                    ->live()
                    ->default(HeistFurniture::ROLE_KEYPAD)
                    ->helperText('Keypad = access gate (by placed furni). Search / Pickup = loot (by furniture type).')
                    ->columnSpanFull(),

                // Keypad role: a specific placed furni + its access code.
                TextInput::make('placed_item_id')
                    ->label('Placed Furni ID')
                    ->numeric()
                    ->visible($isKeypad)
                    ->required($isKeypad)
                    ->dehydrated($isKeypad)
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'That placed furni is already a keypad.'])
                    ->helperText('items.id of the specific placed keypad furni in the room.')
                    ->columnSpanFull(),

                TextInput::make('next_key')
                    ->label('Access Code')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99)
                    ->visible($isKeypad)
                    ->dehydrated($isKeypad)
                    ->helperText('Two-digit code (0-99). The emulator re-rolls it on every open, so a value set here holds only until the next trigger. Leave blank to let gameplay set it.')
                    ->columnSpanFull(),

                // Search / Pickup role: a furniture type.
                Select::make('item_base_id')
                    ->label('Furniture')
                    ->relationship(
                        name: 'itemBase',
                        titleAttribute: 'item_name',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('item_name'),
                    )
                    ->searchable()
                    ->preload()
                    ->visible($isLoot)
                    ->required($isLoot)
                    ->dehydrated($isLoot)
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'That furniture is already attached to a heist.'])
                    ->helperText('items_base row to attach. Each furni base can belong to only one heist.')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('role')
                    ->label('Role')
                    ->formatStateUsing(fn (string $state) => HeistFurniture::ROLE_OPTIONS[$state] ?? $state)
                    ->badge()
                    ->sortable(),

                TextColumn::make('target')
                    ->label('Furniture / Placed')
                    ->getStateUsing(fn ($record) => $record->role === HeistFurniture::ROLE_KEYPAD
                        ? ('Placed #' . $record->placed_item_id)
                        : ($record->itemBase?->item_name ?? ('Base #' . $record->item_base_id)))
                    ->searchable(false),

                TextColumn::make('next_key')
                    ->label('Access Code')
                    ->getStateUsing(fn ($record) => ($record->role === HeistFurniture::ROLE_KEYPAD && $record->next_key !== null)
                        ? str_pad((string) $record->next_key, 2, '0', STR_PAD_LEFT)
                        : '')
                    ->sortable(),

                TextColumn::make('room_id')
                    ->label('Room')
                    ->getStateUsing(fn ($record) => $record->role === HeistFurniture::ROLE_KEYPAD
                        ? (string) ($record->room_id ?? '')
                        : '')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
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
