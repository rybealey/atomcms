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
        $isPlacement = fn (callable $get): bool => in_array($get('role'), HeistFurniture::PLACEMENT_ROLES, true);
        $isKeypad = fn (callable $get): bool => $get('role') === HeistFurniture::ROLE_KEYPAD;
        $isLoot = fn (callable $get): bool => ! in_array($get('role'), HeistFurniture::PLACEMENT_ROLES, true);
        $isSafe = fn (callable $get): bool => $get('role') === HeistFurniture::ROLE_SAFE;
        $isVault = fn (callable $get): bool => $get('role') === HeistFurniture::ROLE_VAULT;
        // Search and Cash Box are both stand-and-search furnitures (they share the dig timer).
        $isSearchable = fn (callable $get): bool => in_array($get('role'), HeistFurniture::SEARCHABLE_ROLES, true);

        return $schema
            ->components([
                Select::make('role')
                    ->label('Role')
                    ->options(HeistFurniture::ROLE_OPTIONS)
                    ->required()
                    ->live()
                    ->default(HeistFurniture::ROLE_KEYPAD)
                    ->helperText('Keypad + Entrance/Exit teleporters are added by placed furni id. Search / Pickup loot is added by furniture type.')
                    ->columnSpanFull(),

                // Placement roles (keypad / entrance / exit): a specific placed furni.
                TextInput::make('placed_item_id')
                    ->label('Placed Furni ID')
                    ->numeric()
                    ->visible($isPlacement)
                    ->required($isPlacement)
                    ->dehydrated($isPlacement)
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'That placed furni is already attached to a heist.'])
                    ->helperText('items.id of the specific placed furni in the room (the keypad, or the entrance/exit teleporter).')
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

                // Search / Cash Box roles: how long the stand-and-search takes.
                TextInput::make('search_duration_seconds')
                    ->label('Search Duration (s)')
                    ->numeric()
                    ->minValue(1)
                    ->default(10)
                    ->visible($isSearchable)
                    ->required($isSearchable)
                    ->dehydrated($isSearchable)
                    ->helperText('How long a player stands and searches this furniture before it pays out.')
                    ->columnSpanFull(),

                // Cash Box role only: currency-only payout. One overall award chance, then a
                // weighted coins-vs-diamonds split, each rolling a random amount in its range.
                TextInput::make('safe_award_chance_pct')
                    ->label('Award Chance %')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(25)
                    ->visible($isSafe)
                    ->required($isSafe)
                    ->dehydrated($isSafe)
                    ->helperText('Chance (0-100) the cash box pays out anything. On a miss the player gets nothing.')
                    ->columnSpanFull(),

                TextInput::make('safe_coins_weight')
                    ->label('Coins Weight')
                    ->numeric()
                    ->minValue(0)
                    ->default(80)
                    ->visible($isSafe)
                    ->dehydrated($isSafe)
                    ->helperText('Relative weight of the coins branch when the cash box pays out. Set both weights to 0 for no payout.'),

                TextInput::make('safe_coins_min')
                    ->label('Coins Min')
                    ->numeric()
                    ->minValue(1)
                    ->default(100)
                    ->visible($isSafe)
                    ->dehydrated($isSafe),

                TextInput::make('safe_coins_max')
                    ->label('Coins Max')
                    ->numeric()
                    ->minValue(1)
                    ->default(500)
                    ->visible($isSafe)
                    ->dehydrated($isSafe)
                    ->helperText('Payout is a random amount between Coins Min and Coins Max (inclusive).'),

                TextInput::make('safe_diamonds_weight')
                    ->label('Diamonds Weight')
                    ->numeric()
                    ->minValue(0)
                    ->default(20)
                    ->visible($isSafe)
                    ->dehydrated($isSafe)
                    ->helperText('Relative weight of the diamonds branch when the cash box pays out.'),

                TextInput::make('safe_diamonds_min')
                    ->label('Diamonds Min')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->visible($isSafe)
                    ->dehydrated($isSafe),

                TextInput::make('safe_diamonds_max')
                    ->label('Diamonds Max')
                    ->numeric()
                    ->minValue(1)
                    ->default(3)
                    ->visible($isSafe)
                    ->dehydrated($isSafe)
                    ->helperText('Payout is a random amount between Diamonds Min and Diamonds Max (inclusive).'),

                // Vault role only: lockpick-cracked, coins-only. The 20% crack
                // chance is fixed in the emulator (not authored here); a hit pays
                // a random amount in [min,max] and empties the vault for the rest
                // of the heist. A miss just burns the lockpick.
                TextInput::make('vault_coins_min')
                    ->label('Coins Min')
                    ->numeric()
                    ->minValue(1)
                    ->default(100)
                    ->visible($isVault)
                    ->required($isVault)
                    ->dehydrated($isVault)
                    ->helperText('Players spend a lockpick to crack it (fixed 20% chance). On a hit the payout is a random amount between Coins Min and Coins Max (inclusive).'),

                TextInput::make('vault_coins_max')
                    ->label('Coins Max')
                    ->numeric()
                    ->minValue(1)
                    ->default(500)
                    ->visible($isVault)
                    ->required($isVault)
                    ->dehydrated($isVault),
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
                    ->getStateUsing(fn ($record) => $record->placed_item_id !== null
                        ? ('Placed #' . $record->placed_item_id)
                        : ($record->itemBase?->item_name ?? ('Base #' . $record->item_base_id)))
                    ->searchable(false),

                TextColumn::make('next_key')
                    ->label('Access Code')
                    ->getStateUsing(fn ($record) => ($record->role === HeistFurniture::ROLE_KEYPAD && $record->next_key !== null)
                        ? str_pad((string) $record->next_key, 2, '0', STR_PAD_LEFT)
                        : '')
                    ->sortable(),

                TextColumn::make('search_duration_seconds')
                    ->label('Search (s)')
                    ->getStateUsing(fn ($record) => in_array($record->role, HeistFurniture::SEARCHABLE_ROLES, true)
                        ? (string) ($record->search_duration_seconds ?? '')
                        : '')
                    ->sortable(),

                TextColumn::make('safe_payout')
                    ->label('Cash Box Payout')
                    ->getStateUsing(function ($record) {
                        if ($record->role !== HeistFurniture::ROLE_SAFE) {
                            return '';
                        }
                        $parts = [];
                        if ((int) $record->safe_coins_weight > 0) {
                            $parts[] = $record->safe_coins_min . '-' . $record->safe_coins_max . ' coins';
                        }
                        if ((int) $record->safe_diamonds_weight > 0) {
                            $parts[] = $record->safe_diamonds_min . '-' . $record->safe_diamonds_max . ' diamonds';
                        }
                        $payout = $parts === [] ? 'nothing' : implode(' / ', $parts);

                        return ((int) $record->safe_award_chance_pct) . '% -> ' . $payout;
                    }),

                TextColumn::make('vault_payout')
                    ->label('Vault Payout')
                    ->getStateUsing(function ($record) {
                        if ($record->role !== HeistFurniture::ROLE_VAULT) {
                            return '';
                        }
                        return $record->vault_coins_min . '-' . $record->vault_coins_max . ' coins (20% crack)';
                    }),

                TextColumn::make('room_id')
                    ->label('Room')
                    ->getStateUsing(fn ($record) => (string) ($record->room_id ?? ''))
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
