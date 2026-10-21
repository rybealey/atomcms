<?php

use App\Actions\Fortify\Controllers\TwoFactorAuthenticatedSessionController;
use App\Http\Controllers\Client\AssetPassController;
use App\Http\Controllers\Client\DeviceReportController;
use App\Http\Controllers\Client\NitroController;
use App\Http\Controllers\Community\CorporationController;
use App\Http\Controllers\Community\GangController;
use App\Http\Controllers\Community\LeaderboardController;
use App\Http\Controllers\Diamonds\DiamondCheckoutController;
use App\Http\Controllers\Diamonds\DiamondCryptoCheckoutController;
use App\Http\Controllers\Diamonds\DiamondReturnController;
use App\Http\Controllers\Diamonds\DiamondStripeWebhookController;
use App\Http\Controllers\Help\WebsiteRulesController;
use App\Http\Controllers\Housekeeping\Api\BadgeController as HousekeepingBadgeController;
use App\Http\Controllers\Housekeeping\Api\DashboardController as HousekeepingDashboardController;
use App\Http\Controllers\Housekeeping\Api\MeController as HousekeepingMeController;
use App\Http\Controllers\Housekeeping\Api\PlayerController as HousekeepingPlayerController;
use App\Http\Controllers\Housekeeping\Api\RoleplayController as HousekeepingRoleplayController;
use App\Http\Controllers\Housekeeping\Api\SettingController as HousekeepingSettingController;
use App\Http\Controllers\Housekeeping\Api\StaffController as HousekeepingStaffController;
use App\Http\Controllers\Housekeeping\HousekeepingController;
use App\Http\Controllers\Miscellaneous\HomeController;
use App\Http\Controllers\Miscellaneous\InstallationController;
use App\Http\Controllers\Miscellaneous\LocaleController;
use App\Http\Controllers\Miscellaneous\MaintenanceController;
use App\Http\Controllers\User\AccountSettingsController;
use App\Http\Controllers\User\BannedController;
use App\Http\Controllers\User\CharacterSwitchController;
use App\Http\Controllers\User\DiscordController;
use App\Http\Controllers\User\ForgotPasswordController;
use App\Http\Controllers\User\MeController;
use App\Http\Controllers\User\PasswordSettingsController;
use App\Http\Controllers\User\ReferralController;
use App\Http\Controllers\User\SessionController;
use App\Http\Controllers\User\TwoFactorAuthenticationController;
use App\Http\Controllers\User\UserReferralController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

// Language route
Route::get('/language/{locale}', LocaleController::class)->name('language.select');

// Diamonds store webhook. Public and signature-verified rather than
// session-authenticated, so it is registered outside every route group
// (maintenance/ban/2FA and auth alike) and CSRF-excluded in VerifyCsrfToken.
Route::post('/webhooks/diamonds-stripe', DiamondStripeWebhookController::class)->name('diamonds.webhook');

// Landing page for the crypto checkout tab (hosted session success/cancel URL).
// Public, and kept outside the maintenance/ban/2FA group: Stripe redirects the
// customer's browser here after they pay, and the confirmation shouldn't be
// gated behind a fresh login or hidden during maintenance. Diamonds are
// credited by the webhook above, not by this page.
Route::get('/diamonds/return', DiamondReturnController::class)->name('diamonds.return');

// Installation routes
Route::prefix('installation')->controller(InstallationController::class)->group(function () {
    Route::get('/', 'index')->name('installation.index');
    Route::get('/step/{step}', 'showStep')->name('installation.show-step');

    Route::post('/start-installation', 'storeInstallationKey')->name('installation.start-installation');
    Route::post('/restart-installation', 'restartInstallation')->name('installation.restart');
    Route::post('/previous-step', 'previousStep')->name('installation.previous-step');
    Route::post('/save-step', 'saveStepSettings')->name('installation.save-step');
    Route::post('/complete', 'completeInstallation')->name('installation.complete');
});

// All routes within this group is protected by maintenance, ban and 2FA middleware
Route::middleware(['maintenance', 'check.ban', 'force.staff.2fa'])->group(function () {
    // Maintenance route
    Route::get('/maintenance', MaintenanceController::class)->name('maintenance.show');

    // Banned route
    Route::get('/banned', BannedController::class)->name('banned.show');

    // Exceptions to the 2FA check and must only be visited if not logged in
    Route::middleware(['guest', 'throttle:15,1'])->withoutMiddleware('force.staff.2fa')->group(function () {
        Route::get('/login', static fn () => to_route('welcome'))->name('login');
        Route::get('/', HomeController::class)->name('welcome');

        Route::get('/register', [RegisteredUserController::class, 'create']);

        Route::post('/register', [RegisteredUserController::class, 'store'])
            ->name('register');

        Route::get('/register/{referral_code}', UserReferralController::class)->name('register.referral');

        // Password
        Route::get('forgot-password', ForgotPasswordController::class)->name('forgot.password.get');
        Route::post('forgot-password', [ForgotPasswordController::class, 'submitForgetPassword'])->middleware('throttle:6,1')->name('forgot.password.post');
        Route::get('reset-password/{token}', [ForgotPasswordController::class, 'showResetPassword'])->middleware('throttle:6,1')->name('reset.password.get');
        Route::post('reset-password/{token}', [ForgotPasswordController::class, 'submitResetPassword'])->middleware('throttle:6,1')->name('reset.password.post');
    });

    // Can only be accessed if logged in
    Route::middleware('auth')->group(function () {
        // pixelrp: the housekeeping panel. A React app served from one Blade
        // shell, talking to a session-authenticated JSON API under the same
        // prefix. Filament keeps /housekeeping/legacy (see the panel provider).
        Route::prefix('housekeeping')->middleware('housekeeping.access')->group(function () {
            Route::prefix('api')->name('housekeeping.api.')->group(function () {
                Route::get('/me', HousekeepingMeController::class)->name('me');
                Route::get('/dashboard', HousekeepingDashboardController::class)->name('dashboard');

                Route::get('/players', [HousekeepingPlayerController::class, 'index'])->name('players.index');
                Route::get('/players/{id}', [HousekeepingPlayerController::class, 'show'])->whereNumber('id')->name('players.show');

                Route::middleware('housekeeping.access:manage_website_settings')->group(function () {
                    Route::get('/settings', [HousekeepingSettingController::class, 'index'])->name('settings.index');
                    Route::put('/settings', [HousekeepingSettingController::class, 'update'])->name('settings.update');
                    Route::get('/settings/changes', [HousekeepingSettingController::class, 'changes'])->name('settings.changes');
                });

                Route::get('/staff', [HousekeepingStaffController::class, 'index'])->name('staff.index');
                Route::put('/staff/permissions/{permission}', [HousekeepingStaffController::class, 'updatePermission'])
                    ->middleware('housekeeping.access:manage_housekeeping_permissions')
                    ->name('staff.permissions.update');

                Route::get('/badges', [HousekeepingBadgeController::class, 'index'])->name('badges.index');

                Route::get('/roleplay/corporations', [HousekeepingRoleplayController::class, 'corporations'])->name('roleplay.corporations');
                Route::get('/roleplay/corporations/{id}', [HousekeepingRoleplayController::class, 'corporation'])->whereNumber('id')->name('roleplay.corporation');
                Route::get('/roleplay/crimes', [HousekeepingRoleplayController::class, 'crimes'])->name('roleplay.crimes');
                // Crimes are the one roleplay resource housekeeping writes
                // directly: nothing caches them, so :charge sees an edit on its
                // next use. Behind the settings permission - editing what the
                // police can charge people with is a hotel-configuration act.
                Route::middleware('housekeeping.access:manage_website_settings')->group(function () {
                    Route::post('/roleplay/crimes', [HousekeepingRoleplayController::class, 'storeCrime'])->name('roleplay.crimes.store');
                    Route::put('/roleplay/crimes/{id}', [HousekeepingRoleplayController::class, 'updateCrime'])->whereNumber('id')->name('roleplay.crimes.update');
                    Route::delete('/roleplay/crimes/{id}', [HousekeepingRoleplayController::class, 'destroyCrime'])->whereNumber('id')->name('roleplay.crimes.destroy');
                });
                Route::get('/roleplay/gangs', [HousekeepingRoleplayController::class, 'gangs'])->name('roleplay.gangs');
                Route::get('/roleplay/gangs/{id}', [HousekeepingRoleplayController::class, 'gang'])->whereNumber('id')->name('roleplay.gang');
            });

            // Everything else under /housekeeping is the SPA shell. `legacy`
            // and `api` are excluded so Filament's routes and the JSON API
            // above are never shadowed by the catch-all.
            Route::get('/{any?}', HousekeepingController::class)
                ->where('any', '^(?!legacy(/|$)|api(/|$)).*$')
                ->name('housekeeping.app');
        });
        Route::prefix('user')->group(function () {
            Route::get('/me', MeController::class)->name('me.show');

            // pixelrp: which of the account's characters the website shows and
            // the next /game load enters as (see MULTI_CHARACTER.md).
            Route::post('/characters/{character}/switch', CharacterSwitchController::class)
                ->whereNumber('character')
                ->middleware('throttle:10,1')
                ->name('characters.switch');
            Route::post('/claim/referral-reward', ReferralController::class)
                ->middleware('throttle:5,1')
                ->name('claim.referral-reward');

            // User settings routes
            Route::prefix('settings')->group(function () {
                Route::get('/account', [AccountSettingsController::class, 'edit'])->name('settings.account.show');
                Route::put('/account', [AccountSettingsController::class, 'update'])->name('settings.account.update');

                Route::get('/password', [PasswordSettingsController::class, 'edit'])->name('settings.password.show');
                Route::put('/password', [PasswordSettingsController::class, 'update'])->name('settings.password.update');

                // pixelrp: where you're logged in, and the log in history
                Route::get('/session-logs', [SessionController::class, 'index'])->name('settings.session-logs');
                Route::delete('/sessions', [SessionController::class, 'destroyOthers'])->name('settings.sessions.destroy-others');
                Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('settings.sessions.destroy');

                Route::get('/two-factor', [TwoFactorAuthenticationController::class, 'index'])->name('settings.two-factor');
                Route::middleware('throttle:two-factor-settings')->group(function () {
                    Route::post('/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])->name('user.two-factor.enable');
                    Route::post('/two-factor-authentication/confirm', [TwoFactorAuthenticationController::class, 'verify'])->name('two-factor.verify');
                    Route::delete('/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])->name('user.two-factor.disable');
                    Route::post('/two-factor-authentication/recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])->name('user.two-factor.recovery-codes');
                });
            });
        });

        // Discord account linking - opened from the game client's Settings
        // window in a new tab (shares the CMS session).
        Route::prefix('discord')->group(function () {
            // Disconnecting happens in-game (wire 3956); the callback is the
            // only page this flow still needs.
            Route::get('/connect', [DiscordController::class, 'connect'])->middleware('throttle:10,1')->name('discord.connect');
            Route::get('/callback', [DiscordController::class, 'callback'])->middleware('throttle:10,1')->name('discord.callback');
        });

        // Leaderboard routes
        Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard.index');

        // pixelrp: the city's corporations and gangs, read from the emulator's
        // roleplay tables. Joining either happens in game.
        Route::get('/corporations', [CorporationController::class, 'index'])->name('corporations.index');
        Route::get('/corporations/{id}', [CorporationController::class, 'show'])->whereNumber('id')->name('corporations.show');
        Route::get('/gangs', GangController::class)->name('gangs.index');

        // Help center. Only the rules page survives the redesign; Terms links
        // still point at it.
        Route::prefix('help-center')->as('help-center.')->withoutMiddleware('check.ban')->group(function () {
            Route::get('/rules', WebsiteRulesController::class)->name('rules.index')->withoutMiddleware('auth');
        });

        // Diamonds store routes - the standalone Stripe purchase flow.
        Route::prefix('diamonds')->group(function () {
            Route::post('/checkout-session', DiamondCheckoutController::class)
                ->name('diamonds.checkout-session')
                ->middleware('throttle:10,1');

            // Crypto (stablecoin) purchases: hosted Checkout opened in a new
            // tab, since crypto is redirect-based and the embedded form can't
            // redirect out of the game iframe. See DiamondCryptoCheckoutController.
            Route::post('/crypto-checkout-session', DiamondCryptoCheckoutController::class)
                ->name('diamonds.crypto-checkout-session')
                ->middleware('throttle:10,1');
        });

        // Client routes. The game lives at /game itself (pixelrp); /game/nitro
        // stays as a redirect so old bookmarks keep working.
        Route::middleware(['findretros.redirect', 'vpn.checker'])->group(function () {
            Route::get('/game', NitroController::class)->name('nitro-client');
            Route::get('/game/asset-pass', AssetPassController::class)->name('nitro-client.asset-pass')
                ->withoutMiddleware('findretros.redirect')->middleware('throttle:30,1');
            // pixelrp: the game page's browser and machine specs, for staff (ConnectionLog).
            Route::post('/game/device', DeviceReportController::class)->name('nitro-client.device')
                ->withoutMiddleware('findretros.redirect')->middleware('throttle:10,1');
            Route::redirect('/game/nitro', '/game', 301);
        });
    });
});

if (Features::enabled(Features::twoFactorAuthentication())) {
    $twoFactorLimiter = config('fortify.limiters.two-factor');

    Route::post('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
        ->middleware(
            array_filter([
                'guest:' . config('fortify.guard'),
                $twoFactorLimiter ? 'throttle:' . $twoFactorLimiter : null,
            ]),
        );
}
