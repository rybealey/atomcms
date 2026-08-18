<?php

namespace App\Filament\Resources\Atom\BetaCodes;

use App\Filament\Resources\Atom\BetaCodes\Pages;
use App\Models\Miscellaneous\WebsiteBetaCode;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WebsiteBetaCodeResource extends Resource
{
    protected static ?string $model = WebsiteBetaCode::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Beta Codes';

    protected static ?string $modelLabel = 'beta code';

    protected static ?string $pluralModelLabel = 'beta codes';

    protected static ?string $slug = 'website/beta-codes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->helperText('The code a player types on the registration form. Must be unique.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Code copied'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (WebsiteBetaCode $record): string => $record->user_id ? 'Redeemed' : 'Available')
                    ->color(fn (string $state): string => $state === 'Redeemed' ? 'gray' : 'success'),

                TextColumn::make('user.username')
                    ->label('Redeemed by')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('claimed')
                    ->label('Redeemed')
                    ->placeholder('All codes')
                    ->trueLabel('Redeemed only')
                    ->falseLabel('Available only')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('user_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                // Only unredeemed codes can be removed — redeemed ones are kept as
                // a record of who used them.
                DeleteAction::make()
                    ->visible(fn (WebsiteBetaCode $record): bool => $record->user_id === null),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebsiteBetaCodes::route('/'),
            'create' => Pages\CreateWebsiteBetaCode::route('/create'),
        ];
    }
}
