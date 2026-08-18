<?php

namespace App\Filament\Resources\Atom\BetaCodes\Pages;

use App\Filament\Resources\Atom\BetaCodes\WebsiteBetaCodeResource;
use App\Models\Miscellaneous\WebsiteBetaCode;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteBetaCodes extends ListRecords
{
    protected static string $resource = WebsiteBetaCodeResource::class;

    protected function getActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate codes')
                ->icon('heroicon-o-sparkles')
                ->schema([
                    TextInput::make('quantity')
                        ->label('How many codes?')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(500)
                        ->default(10)
                        ->required(),

                    TextInput::make('prefix')
                        ->label('Prefix (optional)')
                        ->maxLength(12)
                        ->helperText('Prepended to each code, e.g. "PXL" → PXL-9K7Q2M.'),
                ])
                ->action(function (array $data): void {
                    $created = static::generateCodes(
                        (int) $data['quantity'],
                        strtoupper(trim((string) ($data['prefix'] ?? ''))),
                    );

                    Notification::make()
                        ->title($created > 0
                            ? "Generated {$created} beta code(s)"
                            : 'No new codes were generated')
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('New code'),
        ];
    }

    /**
     * Generate up to $quantity unique, unredeemed beta codes and insert them.
     * Returns the number actually inserted (collisions with existing codes are
     * skipped rather than retried indefinitely).
     */
    private static function generateCodes(int $quantity, string $prefix): int
    {
        // No easily-confused characters (0/O, 1/I) so codes read cleanly aloud.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $alphabetMax = strlen($alphabet) - 1;

        $make = static function () use ($prefix, $alphabet, $alphabetMax): string {
            $body = '';

            for ($i = 0; $i < 8; $i++) {
                $body .= $alphabet[random_int(0, $alphabetMax)];
            }

            return $prefix !== '' ? "{$prefix}-{$body}" : $body;
        };

        // Build a de-duplicated candidate set; the guard bounds the loop so an
        // exhausted keyspace can never spin forever.
        $candidates = [];
        $guard = $quantity * 20;

        while (count($candidates) < $quantity && $guard-- > 0) {
            $candidates[$make()] = true;
        }

        $candidates = array_keys($candidates);

        $existing = WebsiteBetaCode::whereIn('code', $candidates)->pluck('code')->all();
        $fresh = array_values(array_diff($candidates, $existing));

        if ($fresh === []) {
            return 0;
        }

        $now = now();

        WebsiteBetaCode::insert(array_map(
            static fn (string $code): array => [
                'code' => $code,
                'user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $fresh,
        ));

        return count($fresh);
    }
}
