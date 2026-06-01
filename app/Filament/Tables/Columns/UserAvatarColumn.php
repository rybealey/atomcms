<?php

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\Column;

class UserAvatarColumn extends Column
{
    protected ?string $avatarOptions = null;

    protected ?string $figurePointer = null;

    protected string $view = 'filament.tables.columns.user-avatar';

    public function getAvatarUrl(): string
    {
        $record = $this->getRecord();

        $figure = ! $this->figurePointer
            ? ($record->look ?? '')
            : (string) data_get($record, $this->figurePointer);

        if (! $figure) {
            return '';
        }

        // Render through the local Nitro-imager (proxied at /imaging/ on the
        // main AND ase vhosts) instead of setting('avatar_imager') - that
        // default points at habbo.com, which cannot draw custom pixelrp
        // looks, so the housekeeping panel showed broken avatars.
        return "/imaging/?figure={$figure}{$this->avatarOptions}";
    }

    public function options(string $avatarOptions): UserAvatarColumn
    {
        $this->avatarOptions = $avatarOptions;

        return $this;
    }

    /**
     * Used to reference the user's figure string through relationships in a Laravel model. By default it will take the look property of the main class.
     */
    public function pointer(string $figurePointer): UserAvatarColumn
    {
        $this->figurePointer = $figurePointer;

        return $this;
    }
}
