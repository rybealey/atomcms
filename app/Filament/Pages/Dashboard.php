<?php

namespace App\Filament\Pages;

use App\Filament\Traits\TranslatableResource;
use Filament\Pages\Dashboard as FilamentDashboard;

class Dashboard extends FilamentDashboard
{
    use TranslatableResource;

    protected static string|\UnitEnum|null $navigationGroup = 'Dashboard';

    protected static ?string $navigationLabel = 'Homepage';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public static string $translateIdentifier = 'dashboard';

    public static string $roleName = 'dashboard';

    public static function canAccess(): bool
    {
        // This fork defines no view::admin::* Gate, so the original ability
        // check was always false (panel landing 403'd for everyone). Gate
        // the page on the same rank-based housekeeping permission the panel
        // itself uses.
        return hasHousekeepingPermission('can_access_housekeeping');
    }
}
