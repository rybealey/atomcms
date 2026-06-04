<?php

namespace App\Filament\Resources\Roleplay\Heists;

use App\Filament\Resources\Roleplay\Heists\Pages\CreateHeist;
use App\Filament\Resources\Roleplay\Heists\Pages\EditHeist;
use App\Filament\Resources\Roleplay\Heists\Pages\ListHeists;
use App\Filament\Resources\Roleplay\Heists\RelationManagers\HeistFurnituresRelationManager;
use App\Filament\Resources\Roleplay\Heists\RelationManagers\HeistRewardsRelationManager;
use App\Models\Roleplay\Heist;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "Roleplay > Heists" housekeeping page. Each heist is a name + success/timing
 * tuning, a weighted reward table (HeistRewardsRelationManager), and a set of
 * furnitures with roles (HeistFurnituresRelationManager): keypad (access gate,
 * keyed by a placed furni id and carrying its access code), search, or pickup
 * (loot, keyed by item base).
 *
 * The emulator's HeistManager loads loot furnitures by item base; the keypad
 * gate validates a clicked placement against its keypad furniture row.
 */
class HeistResource extends Resource
{
    protected static ?string $model = Heist::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Roleplay';

    public static string $translateIdentifier = 'heists';

    protected static ?string $slug = 'roleplay/heists';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Staff Label')
                    ->required()
                    ->maxLength(120)
                    ->helperText('Internal label only. Never shown to players. Attach furnitures (keypad / search / pickup) in the Furnitures tab after saving.')
                    ->columnSpanFull(),

                Grid::make(3)
                    ->schema([
                        TextInput::make('search_seconds')
                            ->label('Access Window (s)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(60)
                            ->helperText('How long the heist stays active after the keypad is cracked (teleporters open, countdown runs).'),

                        TextInput::make('cooldown_seconds')
                            ->label('Heist Cooldown (s)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(900)
                            ->helperText('After the heist ends, how long before it can be triggered again.'),

                        TextInput::make('find_chance_pct')
                            ->label('Search Success Chance %')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(70)
                            ->helperText('Chance a Search furniture pays off (with the weighted Rewards tab). Each Search furniture sets its own search time in the Furnitures tab.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Staff Label')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('furnitures_count')
                    ->label('Furnitures')
                    ->counts('furnitures')
                    ->sortable(),

                TextColumn::make('find_chance_pct')
                    ->label('Success %')
                    ->sortable()
                    ->suffix('%'),

                TextColumn::make('rewards_count')
                    ->label('Rewards')
                    ->counts('rewards')
                    ->sortable(),

                TextColumn::make('search_seconds')
                    ->label('Duration (s)')
                    ->toggleable(),

                TextColumn::make('cooldown_seconds')
                    ->label('Cooldown (s)')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Last Edited')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
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
            'index' => ListHeists::route('/'),
            'create' => CreateHeist::route('/create'),
            'edit' => EditHeist::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            HeistFurnituresRelationManager::class,
            HeistRewardsRelationManager::class,
        ];
    }
}
