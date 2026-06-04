<?php

namespace App\Filament\Resources\Roleplay\HeistKeypads;

use App\Filament\Resources\Roleplay\HeistKeypads\Pages\EditHeistKeypad;
use App\Filament\Resources\Roleplay\HeistKeypads\Pages\ListHeistKeypads;
use App\Models\Roleplay\HeistKeypad;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "Roleplay > Heist Keypads" housekeeping page. One row per placed keypad
 * furni, showing that placement's current two-digit access code. Rows are
 * created and re-rolled by the emulator (KeypadManager) on every keypad open;
 * staff use this page to read the active code or override it between triggers.
 *
 * There is no Create action — placements come from gameplay. Delete is
 * available to clear orphaned rows (e.g. a keypad that was picked up).
 *
 * Foundation status: the keypad gate currently only shows ACCESS GRANTED /
 * DENIED client-side. The room-access mechanic a granted code will unlock is
 * future work.
 */
class HeistKeypadResource extends Resource
{
    protected static ?string $model = HeistKeypad::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Roleplay';

    public static string $translateIdentifier = 'heist-keypads';

    protected static ?string $slug = 'roleplay/heist-keypads';

    protected static ?string $navigationLabel = 'Heist Keypads';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('placed_item_id')
                    ->label('Placed Furni ID')
                    ->disabled()
                    ->helperText('items.id of the placed keypad. Set by gameplay; not editable.'),

                TextInput::make('room_id')
                    ->label('Room ID')
                    ->disabled()
                    ->helperText('Room the keypad is placed in. Set by gameplay; not editable.'),

                TextInput::make('next_key')
                    ->label('Access Code')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99)
                    ->helperText('Two-digit code (0-99) the keypad will accept. The emulator re-rolls this on every keypad open, so an override here holds only until the next trigger.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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

    public static function getPages(): array
    {
        return [
            'index' => ListHeistKeypads::route('/'),
            'edit' => EditHeistKeypad::route('/{record}/edit'),
        ];
    }
}
