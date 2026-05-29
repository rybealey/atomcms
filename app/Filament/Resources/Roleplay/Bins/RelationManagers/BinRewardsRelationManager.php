<?php

namespace App\Filament\Resources\Roleplay\Bins\RelationManagers;

use App\Models\Roleplay\BinReward;
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
 * Sub-form for editing the weighted reward table of a bin. Lives under
 * the parent {@link \App\Filament\Resources\Roleplay\Bins\BinResource}
 * edit page.
 *
 * The {@code reward_type} radio gates which option set is shown for
 * {@code reward_ref}:
 *   - {@code backpack_item} -> a fixed whitelist mirroring the
 *     emulator's BackpackCatalog ({@link BinReward::BACKPACK_ITEM_OPTIONS}).
 *   - {@code zara_ltd_token} -> a dropdown of LTD Zara clothing offers
 *     ({@code zara_clothing_offers.is_limited = 1}). The chosen
 *     {@code zara_clothing_offers.id} becomes the {@code reward_ref};
 *     the plugin resolves it to the {@code zara_token_<clothingId>}
 *     backpack key at grant time.
 *
 * "Nothing" outcomes aren't authored here — they fall out of the
 * find-chance roll on the parent bin (find_chance_pct < 100 means
 * some dives return empty-handed).
 */
class BinRewardsRelationManager extends RelationManager
{
    protected static string $relationship = 'rewards';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('reward_type')
                    ->label('Reward Type')
                    ->options([
                        BinReward::REWARD_TYPE_BACKPACK_ITEM => 'Backpack Item',
                        BinReward::REWARD_TYPE_ZARA_LTD_TOKEN => 'Zara LTD Token',
                    ])
                    ->required()
                    ->live()
                    ->default(BinReward::REWARD_TYPE_BACKPACK_ITEM)
                    ->columnSpanFull(),

                Select::make('reward_ref')
                    ->label('Item / Token')
                    ->required()
                    ->options(function (callable $get) {
                        if ($get('reward_type') === BinReward::REWARD_TYPE_ZARA_LTD_TOKEN) {
                            return DB::table('zara_clothing_offers')
                                ->where('is_limited', 1)
                                ->orderBy('display_name')
                                ->pluck('display_name', 'id')
                                ->toArray();
                        }
                        return BinReward::BACKPACK_ITEM_OPTIONS;
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
                            ->helperText('Relative weight in the post-find-chance roll.'),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->disabled(fn (callable $get) => $get('reward_type') === BinReward::REWARD_TYPE_ZARA_LTD_TOKEN)
                            ->dehydrateStateUsing(fn ($state, callable $get) =>
                                $get('reward_type') === BinReward::REWARD_TYPE_ZARA_LTD_TOKEN ? 1 : $state)
                            ->helperText('Forced to 1 for LTD tokens.'),
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
                        BinReward::REWARD_TYPE_BACKPACK_ITEM => 'Backpack',
                        BinReward::REWARD_TYPE_ZARA_LTD_TOKEN => 'Zara LTD',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('reward_ref')
                    ->label('Item / Token')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->reward_type === BinReward::REWARD_TYPE_BACKPACK_ITEM) {
                            return BinReward::BACKPACK_ITEM_OPTIONS[$state] ?? $state;
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
