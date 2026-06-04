<?php

namespace App\Filament\Resources\Roleplay\Heists;

use App\Filament\Resources\Roleplay\Heists\Pages\CreateHeist;
use App\Filament\Resources\Roleplay\Heists\Pages\EditHeist;
use App\Filament\Resources\Roleplay\Heists\Pages\ListHeists;
use App\Filament\Resources\Roleplay\Heists\RelationManagers\HeistKeypadsRelationManager;
use App\Filament\Resources\Roleplay\Heists\RelationManagers\HeistRewardsRelationManager;
use App\Models\Roleplay\Heist;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Roleplay > Heists" housekeeping page. Foundation clone of
 * {@link \App\Filament\Resources\Roleplay\Bins\BinResource}: an
 * admin-curated list of furni that act as heist targets, with a per-target
 * success probability + a weighted reward table (managed via the
 * {@link HeistRewardsRelationManager} sub-form).
 *
 * The emulator's HeistManager loads {@code rp_heists} on boot (and on
 * {@code :reloadheists}) keyed by the rows here. The in-world trigger that
 * starts a heist is a future UI component (server seam:
 * HeistManager.tryStart); there is no furni interaction-type binding yet.
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
                Select::make('item_base_id')
                    ->label('Heist Furniture')
                    ->relationship(
                        name: 'itemBase',
                        titleAttribute: 'item_name',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('item_name'),
                    )
                    ->searchable()
                    ->required()
                    ->preload()
                    ->helperText('Pick the items_base row that should act as a heist target. The in-world trigger is a future UI component, so no interaction_type binding is required yet.')
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->label('Staff Label')
                    ->required()
                    ->maxLength(120)
                    ->helperText('Internal label only. Never shown to players.'),

                Grid::make(3)
                    ->schema([
                        TextInput::make('find_chance_pct')
                            ->label('Success Chance %')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(70)
                            ->helperText('First-roll: did the heist pay off?'),

                        TextInput::make('search_seconds')
                            ->label('Duration (s)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(10),

                        TextInput::make('cooldown_seconds')
                            ->label('Cooldown (s)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(900)
                            ->helperText('Per-target global cooldown after a successful heist.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon')
                    ->getStateUsing(fn ($record) => url($record->itemBase?->icon()))
                    ->size('25px')
                    ->label('Icon')
                    ->circular(),

                TextColumn::make('name')
                    ->label('Staff Label')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('itemBase.item_name')
                    ->label('Furniture')
                    ->sortable()
                    ->searchable(),

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
            HeistRewardsRelationManager::class,
            HeistKeypadsRelationManager::class,
        ];
    }
}
