<?php

namespace App\Filament\Resources\Roleplay\ChargeTypes;

use App\Filament\Resources\Roleplay\ChargeTypes\Pages\CreateChargeType;
use App\Filament\Resources\Roleplay\ChargeTypes\Pages\EditChargeType;
use App\Filament\Resources\Roleplay\ChargeTypes\Pages\ListChargeTypes;
use App\Models\Roleplay\ChargeType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "Roleplay > Charge Types" housekeeping page. Admin-curated catalogue of the
 * crimes a clocked-in officer can apply with {@code :charge <user> <crime>}.
 *
 * The emulator loads {@code rp_charge_types} on boot and on {@code :reloadcharges}
 * via the plugin's ChargeCatalog. Rows flagged {@code is_system} (e.g. 911abuse)
 * are referenced by name in plugin code: their crime_key cannot be renamed and
 * they cannot be deleted, but their fine / jail / enabled flags stay editable.
 */
class ChargeTypeResource extends Resource
{
    protected static ?string $model = ChargeType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Roleplay';

    public static string $translateIdentifier = 'charge-types';

    protected static ?string $slug = 'roleplay/charge-types';

    protected static ?string $modelLabel = 'charge';

    protected static ?string $pluralModelLabel = 'charges';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextInput::make('crime_key')
                            ->label('Crime Key')
                            ->required()
                            ->maxLength(32)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?ChargeType $record) => $record?->is_system)
                            ->dehydrated()
                            ->helperText('Stable lowercase key stored on each charge (e.g. "robbery"). Cannot be changed for system crimes.'),

                        TextInput::make('short_key')
                            ->label('Short Key')
                            ->required()
                            ->maxLength(16)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Shorthand a cop can type, e.g. ":charge x rob".'),
                    ]),

                TextInput::make('display_name')
                    ->label('Display Name')
                    ->required()
                    ->maxLength(64)
                    ->helperText('Player-facing name shown in charge emotes and the Wanted List.'),

                Grid::make(2)
                    ->schema([
                        TextInput::make('coin_cost')
                            ->label('Ticket Value (coins)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Coin fine per charge. Leave blank for a non-ticketable crime (blocks the whole ticket).'),

                        TextInput::make('jail_minutes')
                            ->label('Jail Minutes')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Display-only, feeds the Wanted List jail-time readout. No arrest mechanic.'),
                    ]),

                Grid::make(2)
                    ->schema([
                        Toggle::make('stackable')
                            ->label('Stackable')
                            ->default(true)
                            ->helperText('Off = capped at one active charge of this type per player.'),

                        Toggle::make('enabled')
                            ->label('Enabled')
                            ->default(true)
                            ->helperText('Off = the emulator ignores this crime entirely on the next reload.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Crime')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('crime_key')
                    ->label('Key')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('short_key')
                    ->label('Short')
                    ->searchable(),

                TextColumn::make('coin_cost')
                    ->label('Ticket Value')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state === null ? 'Non-ticketable' : $state . ' coins')
                    ->color(fn ($state) => $state === null ? 'danger' : null)
                    ->badge(),

                TextColumn::make('jail_minutes')
                    ->label('Jail (min)')
                    ->sortable(),

                IconColumn::make('stackable')
                    ->label('Stackable')
                    ->boolean(),

                IconColumn::make('enabled')
                    ->label('Enabled')
                    ->boolean(),

                IconColumn::make('is_system')
                    ->label('System')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-minus')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Last Edited')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (ChargeType $record) => ! $record->is_system),
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
            'index' => ListChargeTypes::route('/'),
            'create' => CreateChargeType::route('/create'),
            'edit' => EditChargeType::route('/{record}/edit'),
        ];
    }
}
