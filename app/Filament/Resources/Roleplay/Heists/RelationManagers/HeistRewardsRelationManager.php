<?php

namespace App\Filament\Resources\Roleplay\Heists\RelationManagers;

use App\Models\Roleplay\HeistReward;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * Sub-form for editing the weighted reward table of a heist. Foundation
 * clone of {@link \App\Filament\Resources\Roleplay\Bins\RelationManagers\BinRewardsRelationManager}.
 *
 * The {@code reward_type} radio gates which option set is shown for
 * {@code reward_ref}:
 *   - {@code backpack_item} -> a fixed whitelist mirroring the emulator's
 *     HeistManager ({@link HeistReward::BACKPACK_ITEM_OPTIONS}).
 *   - {@code zara_ltd_token} -> a dropdown of LTD Zara clothing offers
 *     ({@code zara_clothing_offers.is_limited = 1}); the chosen id becomes
 *     {@code reward_ref}.
 *   - {@code currency} -> coins or diamonds.
 *
 * "Nothing" outcomes aren't authored here — they fall out of the
 * success-chance roll on the parent heist (find_chance_pct < 100 means
 * some attempts come up empty).
 */
class HeistRewardsRelationManager extends RelationManager
{
    protected static string $relationship = 'rewards';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('reward_type')
                    ->label('Reward Type')
                    ->options([
                        HeistReward::REWARD_TYPE_BACKPACK_ITEM => 'Backpack Item',
                        HeistReward::REWARD_TYPE_ZARA_LTD_TOKEN => 'Zara LTD Token',
                        HeistReward::REWARD_TYPE_CURRENCY => 'Currency (Coins / Diamonds)',
                    ])
                    ->required()
                    ->live()
                    ->default(HeistReward::REWARD_TYPE_BACKPACK_ITEM)
                    ->columnSpanFull(),

                Select::make('reward_ref')
                    ->label('Item / Token / Currency')
                    ->required()
                    ->options(function (callable $get) {
                        if ($get('reward_type') === HeistReward::REWARD_TYPE_ZARA_LTD_TOKEN) {
                            return DB::table('zara_clothing_offers')
                                ->where('is_limited', 1)
                                ->orderBy('display_name')
                                ->pluck('display_name', 'id')
                                ->toArray();
                        }
                        if ($get('reward_type') === HeistReward::REWARD_TYPE_CURRENCY) {
                            return HeistReward::CURRENCY_OPTIONS;
                        }
                        return HeistReward::BACKPACK_ITEM_OPTIONS;
                    })
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        TextInput::make('weight')
                            ->label('Weight')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(100)
                            ->helperText('Relative weight in the post-success-chance roll.'),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->disabled(fn (callable $get) => $get('reward_type') === HeistReward::REWARD_TYPE_ZARA_LTD_TOKEN)
                            ->dehydrateStateUsing(fn ($state, callable $get) =>
                                $get('reward_type') === HeistReward::REWARD_TYPE_ZARA_LTD_TOKEN ? 1 : $state)
                            ->helperText('Forced to 1 for LTD tokens. For Currency, this is the coin or diamond quantity granted.'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reward_ref')
            ->columns([
                TextColumn::make('reward_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        HeistReward::REWARD_TYPE_BACKPACK_ITEM => 'Backpack',
                        HeistReward::REWARD_TYPE_ZARA_LTD_TOKEN => 'Zara LTD',
                        HeistReward::REWARD_TYPE_CURRENCY => 'Currency',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('reward_ref')
                    ->label('Item / Token / Currency')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->reward_type === HeistReward::REWARD_TYPE_BACKPACK_ITEM) {
                            return HeistReward::BACKPACK_ITEM_OPTIONS[$state] ?? $state;
                        }
                        if ($record->reward_type === HeistReward::REWARD_TYPE_CURRENCY) {
                            return HeistReward::CURRENCY_OPTIONS[$state] ?? $state;
                        }
                        $display = DB::table('zara_clothing_offers')
                            ->where('id', $state)
                            ->value('display_name');
                        return $display ?: ("Offer #" . $state);
                    })
                    ->searchable(),

                TextColumn::make('weight')
                    ->label('Weight')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
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
