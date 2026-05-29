<?php

namespace App\Filament\Resources\Roleplay\Bins;

use App\Filament\Resources\Roleplay\Bins\Pages\CreateBin;
use App\Filament\Resources\Roleplay\Bins\Pages\EditBin;
use App\Filament\Resources\Roleplay\Bins\Pages\ListBins;
use App\Filament\Resources\Roleplay\Bins\RelationManagers\BinRewardsRelationManager;
use App\Models\Roleplay\Bin;
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
 * "Roleplay > Bins" housekeeping page. Admin-curated list of furni that
 * behave as Dumpster Diving bins, with a per-bin probability of finding
 * something + a weighted reward table (managed via the
 * {@link BinRewardsRelationManager} sub-form).
 *
 * Wired to the emulator side via {@code items_base.interaction_type =
 * "rp_dumpster"} on the chosen furni; the plugin's
 * DumpsterDivingManager loads {@code rp_bins} on boot (and on
 * {@code :reloadbins}) keyed by the rows here.
 */
class BinResource extends Resource
{
    protected static ?string $model = Bin::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trash';

    protected static string|\UnitEnum|null $navigationGroup = 'Roleplay';

    public static string $translateIdentifier = 'bins';

    protected static ?string $slug = 'roleplay/bins';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_base_id')
                    ->label('Bin Furniture')
                    ->relationship(
                        name: 'itemBase',
                        titleAttribute: 'item_name',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('item_name'),
                    )
                    ->searchable()
                    ->required()
                    ->preload()
                    ->helperText('Pick the items_base row that should behave as a bin. Set its interaction_type to "rp_dumpster" so the emulator loads the InteractionPixelRPDumpster class for it.')
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->label('Staff Label')
                    ->required()
                    ->maxLength(120)
                    ->helperText('Internal label only. Never shown to players.'),

                Grid::make(3)
                    ->schema([
                        TextInput::make('find_chance_pct')
                            ->label('Find Chance %')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(70)
                            ->helperText('First-roll: did they find anything?'),

                        TextInput::make('search_seconds')
                            ->label('Search Duration (s)')
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
                            ->helperText('Per-bin global cooldown after a successful search.'),
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
                    ->label('Find %')
                    ->sortable()
                    ->suffix('%'),

                TextColumn::make('rewards_count')
                    ->label('Rewards')
                    ->counts('rewards')
                    ->sortable(),

                TextColumn::make('search_seconds')
                    ->label('Search (s)')
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
            'index' => ListBins::route('/'),
            'create' => CreateBin::route('/create'),
            'edit' => EditBin::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            BinRewardsRelationManager::class,
        ];
    }
}
