<?php

namespace App\Filament\Resources\Roleplay\Heists\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Placed keypads for this heist and their current two-digit access codes.
 * One row per placed keypad furni of this heist's furniture (linked by
 * item_base_id); each placement carries its own code, so the same keypad
 * dropped in five rooms gates five different codes.
 *
 * Rows are created and re-rolled by the emulator (KeypadManager) on every
 * keypad open — there is no Create action; placements come from gameplay.
 * Staff use the Edit action to read the active code or override it (an
 * override holds only until the next trigger re-rolls it), and Delete to
 * clear orphaned rows (e.g. a keypad that was picked up).
 *
 * Foundation status: a granted code currently only shows ACCESS GRANTED /
 * DENIED client-side. The room-access mechanic it will unlock is future work.
 */
class HeistKeypadsRelationManager extends RelationManager
{
    protected static string $relationship = 'keypads';

    protected static ?string $title = 'Keypads';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('room_id')
                    ->label('Room ID')
                    ->disabled()
                    ->helperText('Room the keypad is placed in. Set by gameplay; not editable.'),

                TextInput::make('placed_item_id')
                    ->label('Placed Furni ID')
                    ->disabled()
                    ->helperText('items.id of the placed keypad. Set by gameplay; not editable.'),

                TextInput::make('next_key')
                    ->label('Access Code')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99)
                    ->helperText('Two-digit code (0-99) this keypad will accept. The emulator re-rolls it on every keypad open, so an override here holds only until the next trigger.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('placed_item_id')
            ->columns([
                TextColumn::make('room_id')
                    ->label('Room')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('placed_item_id')
                    ->label('Placed Furni')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('next_key')
                    ->label('Access Code')
                    ->formatStateUsing(fn ($state) => str_pad((string) $state, 2, '0', STR_PAD_LEFT))
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Rolled')
                    ->dateTime()
                    ->sortable(),
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
