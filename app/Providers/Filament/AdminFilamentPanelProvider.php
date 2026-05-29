<?php

namespace App\Providers\Filament;

use App\Http\Middleware\HousekeepingAuthenticate;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminFilamentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $housekeepingDomain = config('habbo.housekeeping.domain');
        $servedAtRoot = filled($housekeepingDomain);

        $middleware = [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ];

        // SSO-only at the domain root: Filament has no login page, so its
        // default Authenticate returns a bare 401 for guests. Swap in a gate
        // that redirects them to the public site to log in instead.
        $authMiddleware = $servedAtRoot
            ? [HousekeepingAuthenticate::class]
            : [Authenticate::class];

        $panel = $panel
            ->default()
            ->id('housekeeping')
            ->brandName('PixelRP')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware($middleware)
            ->authMiddleware($authMiddleware)
            ->plugins([]);

        if ($servedAtRoot) {
            // Production: the panel IS https://ase.pixelrp.co/. Domain-locking
            // keeps every panel route bound to this host, so pixelrp.co
            // routing is completely untouched. No Filament login page; auth
            // is the shared SSO session from the public site.
            return $panel
                ->domain($housekeepingDomain)
                ->path('');
        }

        // Local/dev (and any misconfig): keep the path-based panel with its
        // own login page so http://localhost/housekeeping still works.
        return $panel
            ->path('housekeeping')
            ->login();
    }
}
