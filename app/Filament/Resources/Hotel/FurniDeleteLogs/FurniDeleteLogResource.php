<?php

namespace App\Filament\Resources\Hotel\FurniDeleteLogs;

use App\Filament\Resources\Hotel\FurniDeleteLogs\Pages\ManageFurniDeleteLogs;
use App\Filament\Traits\TranslatableResource;
use App\Models\FurniDeleteLog;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * pixelrp: what players have destroyed with the inventory's trash bin.
 *
 * Read-only, like the other pages in Logs - the emulator writes these rows and
 * nothing here should edit or remove them. There is no undo for the deletion
 * itself, so the log is the only record left, and a staff member tidying it
 * away would be destroying evidence rather than clutter.
 */
class FurniDeleteLogResource extends Resource
{
    use TranslatableResource;

    protected static ?string $model = FurniDeleteLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trash';

    protected static string|\UnitEnum|null $navigationGroup = 'Logs';

    public static string $translateIdentifier = 'furni-delete-logs';

    protected static ?string $slug = 'logs/furni-deletions';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('deleted_at', 'desc')
            ->columns([
                // The STORED username, not user.username: this is a record of
                // what was true when the button was pressed, and a character
                // renamed since should not silently rewrite its own history.
                TextColumn::make('username')
                    ->label(__('filament::resources.columns.character'))
                    ->searchable(),

                // One account can hold up to three characters, so the character
                // name alone answers "who destroyed this" but not "what has
                // this player destroyed". Not sortable or searchable - it is
                // computed per row rather than selected, so there is no column
                // for the database to order by; the Account filter below is the
                // way to search it.
                TextColumn::make('account')
                    ->label(__('filament::resources.columns.account'))
                    ->state(fn (FurniDeleteLog $record): string => $record->user?->parent?->username
                        ?? $record->user?->username
                        ?? $record->username)
                    ->toggleable(),

                TextColumn::make('item_name')
                    ->label(__('filament::resources.columns.item_name'))
                    ->searchable(),

                TextColumn::make('item_id')
                    ->label(__('filament::resources.columns.item_id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('definition_id')
                    ->label(__('filament::resources.columns.definition_id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label(__('filament::resources.columns.deleted_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                // Everything every character on one account has destroyed.
                // Typing any one of their names finds the rest: the name is
                // resolved to its account root, and characters() collects the
                // root plus its children.
                Filter::make('account')
                    ->schema([
                        TextInput::make('username')
                            ->label(__('filament::resources.filters.account_username')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $username = trim((string) ($data['username'] ?? ''));

                        if ($username === '') {
                            return $query;
                        }

                        $user = User::query()->where('username', $username)->first();

                        // An unknown name must match nothing. Returning the
                        // query untouched would quietly show every deletion in
                        // the hotel and read as "this player destroyed all of
                        // this".
                        if ($user === null) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->whereIn('user_id', $user->characters()->pluck('id'));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $username = trim((string) ($data['username'] ?? ''));

                        if ($username === '') {
                            return null;
                        }

                        // __() is declared as array|string|null, so it cannot
                        // be concatenated without narrowing it first.
                        $label = __('filament::resources.filters.account_username');

                        return is_string($label) ? $label . ': ' . $username : $username;
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        // user.parent feeds the Account column; without it every row on the
        // page runs its own pair of queries.
        return parent::getEloquentQuery()->with('user.parent');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFurniDeleteLogs::route('/'),
        ];
    }
}
