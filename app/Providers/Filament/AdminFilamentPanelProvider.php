<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectHousekeepingGuestsToLogin;
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

        if ($servedAtRoot) {
            // SSO-only: bounce unauthenticated visitors to the public site to
            // log in. Sits after StartSession (guard resolved) and before the
            // Filament auth middleware.
            $middleware[] = RedirectHousekeepingGuestsToLogin::class;
        }

        $panel = $panel
            ->default()
            ->id('housekeeping')
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
            ->authMiddleware([
                Authenticate::class,
            ])
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
