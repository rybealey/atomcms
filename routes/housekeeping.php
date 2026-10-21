<?php

use App\Http\Controllers\Housekeeping\Api\AccessController;
use App\Http\Controllers\Housekeeping\Api\AuditController;
use App\Http\Controllers\Housekeeping\Api\CatalogController;
use App\Http\Controllers\Housekeeping\Api\ChatLogController;
use App\Http\Controllers\Housekeeping\Api\ContentController;
use App\Http\Controllers\Housekeeping\Api\CorporationController;
use App\Http\Controllers\Housekeeping\Api\DisciplineController;
use App\Http\Controllers\Housekeeping\Api\EconomyController;
use App\Http\Controllers\Housekeeping\Api\EmulatorSettingController;
use App\Http\Controllers\Housekeeping\Api\GangController;
use App\Http\Controllers\Housekeeping\Api\JusticeController;
use App\Http\Controllers\Housekeeping\Api\LogController;
use App\Http\Controllers\Housekeeping\Api\MeController;
use App\Http\Controllers\Housekeeping\Api\NewsController;
use App\Http\Controllers\Housekeeping\Api\PlayerActionController;
use App\Http\Controllers\Housekeeping\Api\PlayerController;
use App\Http\Controllers\Housekeeping\Api\PlayerTabController;
use App\Http\Controllers\Housekeeping\Api\RoomController;
use App\Http\Controllers\Housekeeping\Api\SavedViewController;
use App\Http\Controllers\Housekeeping\Api\SecurityController;
use App\Http\Controllers\Housekeeping\Api\SettingController;
use App\Http\Controllers\Housekeeping\Api\SitchController;
use App\Http\Controllers\Housekeeping\Api\StaffController;
use App\Http\Controllers\Housekeeping\Api\SupportController;
use App\Http\Controllers\Housekeeping\Api\TicketController;
use App\Http\Controllers\Housekeeping\Api\TodayController;
use App\Http\Controllers\Housekeeping\Api\WebsiteController;
use App\Http\Controllers\Housekeeping\Api\WordFilterController;
use App\Http\Controllers\Housekeeping\HousekeepingController;
use Illuminate\Support\Facades\Route;

/*
 * pixelrp: the housekeeping panel. One React app (resources/housekeeping)
 * served from a Blade shell at /housekeeping, talking to the session JSON API
 * below. Every route needs can_access_housekeeping; a route that names a
 * capability (`housekeeping.access:manage_bans`) needs that one too. Actions
 * that mirror an in-game ability check permissions_commands in the controller
 * (GameAbilities), so a rank does on the web what it can do in the game.
 */
Route::prefix('housekeeping')->middleware('housekeeping.access')->group(function () {
    Route::prefix('api')->name('housekeeping.api.')->group(function () {
        Route::get('/me', MeController::class)->name('me');
        Route::get('/me/pulse', [MeController::class, 'pulse'])->name('me.pulse');
        Route::get('/today', TodayController::class)->name('today');
        Route::get('/search', [PlayerController::class, 'search'])->name('search');

        // People
        Route::get('/players', [PlayerController::class, 'index'])->name('players.index');
        Route::get('/players/filters', [PlayerController::class, 'filters'])->name('players.filters');
        Route::post('/players/alert', [PlayerActionController::class, 'bulkAlert'])->name('players.bulk-alert');
        Route::prefix('players/{id}')->whereNumber('id')->name('players.')->group(function () {
            Route::get('/', [PlayerController::class, 'show'])->name('show');
            Route::get('/card', [PlayerController::class, 'card'])->name('card');
            Route::get('/peek', [PlayerController::class, 'peek'])->name('peek');
            Route::get('/timeline', [PlayerTabController::class, 'timeline'])->name('timeline');
            Route::get('/money', [PlayerTabController::class, 'money'])->name('money');
            Route::get('/justice', [PlayerTabController::class, 'justice'])->name('justice');
            Route::get('/chat', [PlayerTabController::class, 'conversations'])->name('chat');
            Route::get('/chat/transcript', [PlayerTabController::class, 'transcript'])->name('chat.transcript');
            Route::get('/security', [PlayerTabController::class, 'security'])->name('security');
            Route::get('/inventory', [PlayerTabController::class, 'inventory'])->name('inventory');
            Route::get('/discipline', [PlayerTabController::class, 'discipline'])->name('discipline');

            Route::post('/alert', [PlayerActionController::class, 'alert'])->name('alert');
            Route::post('/disconnect', [PlayerActionController::class, 'disconnect'])->name('disconnect');
            Route::post('/coins', [PlayerActionController::class, 'coins'])->name('coins');
            Route::post('/badges', [PlayerActionController::class, 'giveBadge'])->name('badges.give');
            Route::post('/badges/take', [PlayerActionController::class, 'takeBadge'])->name('badges.take');
            Route::post('/ban', [PlayerActionController::class, 'ban'])->name('ban');
            Route::post('/unban', [PlayerActionController::class, 'unban'])->name('unban');
            Route::post('/mute', [PlayerActionController::class, 'mute'])->name('mute');
            Route::post('/notes', [PlayerActionController::class, 'note'])->name('notes.store');
            Route::patch('/notes/{note}', [PlayerActionController::class, 'updateNote'])->whereNumber('note')->name('notes.update');
            Route::post('/links', [PlayerActionController::class, 'reviewLink'])->name('links');
        });
        Route::get('/discipline', DisciplineController::class)->middleware('housekeeping.access:manage_bans')->name('discipline');
        Route::post('/discipline/bans/{id}/lift', [DisciplineController::class, 'lift'])->whereNumber('id')->middleware('housekeeping.access:manage_bans')->name('discipline.lift');
        Route::get('/security', SecurityController::class)->middleware('housekeeping.access:edit_user')->name('security');
        Route::post('/security/review', [SecurityController::class, 'review'])->middleware('housekeeping.access:edit_user')->name('security.review');

        // Moderation
        Route::middleware('housekeeping.access:manage_support')->group(function () {
            Route::get('/support', [SupportController::class, 'index'])->name('support.index');
            Route::get('/support/{id}', [SupportController::class, 'show'])->whereNumber('id')->name('support.show');
            Route::post('/support/{id}/reply', [SupportController::class, 'reply'])->whereNumber('id')->name('support.reply');
            Route::post('/support/{id}/resolve', [SupportController::class, 'resolve'])->whereNumber('id')->name('support.resolve');
            // Tickets are read here only: the emulator holds open and picked
            // tickets in memory (ModerationManager), so picking up and closing
            // happen in the in-game mod tool.
            Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
            Route::get('/tickets/{id}', [TicketController::class, 'show'])->whereNumber('id')->name('tickets.show');
        });
        Route::get('/chatlogs', ChatLogController::class)->name('chatlogs');
        Route::get('/sitch', [SitchController::class, 'index'])->name('sitch.index');
        Route::post('/sitch/posts/{id}/hide', [SitchController::class, 'hidePost'])->whereNumber('id')->name('sitch.hide');
        Route::post('/sitch/posts/{id}/restore', [SitchController::class, 'restorePost'])->whereNumber('id')->name('sitch.restore');
        Route::post('/sitch/photos/{id}/visibility', [SitchController::class, 'photoVisibility'])->whereNumber('id')->middleware('housekeeping.access:manage_camera_web')->name('sitch.photo');
        Route::post('/sitch/tags', [SitchController::class, 'suppressTag'])->name('sitch.tags');

        // City
        Route::get('/justice', [JusticeController::class, 'index'])->name('justice.index');
        Route::post('/justice/crimes', [JusticeController::class, 'storeCrime'])->name('justice.crimes.store');
        Route::put('/justice/crimes/{id}', [JusticeController::class, 'updateCrime'])->whereNumber('id')->name('justice.crimes.update');
        Route::delete('/justice/crimes/{id}', [JusticeController::class, 'destroyCrime'])->whereNumber('id')->name('justice.crimes.destroy');
        Route::get('/corporations', [CorporationController::class, 'index'])->name('corporations.index');
        Route::get('/corporations/{id}', [CorporationController::class, 'show'])->whereNumber('id')->name('corporations.show');
        Route::get('/gangs', [GangController::class, 'index'])->name('gangs.index');
        Route::get('/gangs/{id}', [GangController::class, 'show'])->whereNumber('id')->name('gangs.show');
        Route::get('/economy', EconomyController::class)->middleware('housekeeping.access:view_economy')->name('economy');
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::get('/rooms/{id}', [RoomController::class, 'show'])->whereNumber('id')->name('rooms.show');

        // Content
        Route::middleware('housekeeping.access:manage_badges')->group(function () {
            Route::get('/badges', [ContentController::class, 'badges'])->name('badges.index');
            Route::post('/badges/drawn/{id}', [ContentController::class, 'reviewDrawnBadge'])->whereNumber('id')->name('badges.review');
            Route::put('/badges/{code}', [ContentController::class, 'updateBadge'])->name('badges.update');
        });
        Route::middleware('housekeeping.access:manage_catalog_pages')->group(function () {
            Route::get('/catalog', [CatalogController::class, 'tree'])->name('catalog.tree');
            Route::get('/catalog/pages/{id}', [CatalogController::class, 'page'])->whereNumber('id')->name('catalog.page');
            Route::put('/catalog/pages/{id}', [CatalogController::class, 'updatePage'])->whereNumber('id')->name('catalog.page.update');
            Route::post('/catalog/pages', [CatalogController::class, 'storePage'])->name('catalog.page.store');
            Route::post('/catalog/pages/reorder', [CatalogController::class, 'reorder'])->name('catalog.pages.reorder');
            Route::get('/catalog/furni', [CatalogController::class, 'furni'])->name('catalog.furni');
            Route::post('/catalog/items', [CatalogController::class, 'storeItem'])->name('catalog.item.store');
            Route::put('/catalog/items/{id}', [CatalogController::class, 'updateItem'])->whereNumber('id')->name('catalog.item.update');
            Route::post('/catalog/reload', [CatalogController::class, 'reload'])->name('catalog.reload');
        });
        Route::get('/achievements', [ContentController::class, 'achievements'])->middleware('housekeeping.access:manage_achievements')->name('achievements.index');
        Route::put('/achievements/{id}', [ContentController::class, 'updateAchievement'])->whereNumber('id')->middleware('housekeeping.access:manage_achievements')->name('achievements.update');
        Route::get('/texts', [ContentController::class, 'texts'])->middleware('housekeeping.access:manage_emulator_texts')->name('texts.index');
        Route::put('/texts/{id}', [ContentController::class, 'updateText'])->whereNumber('id')->middleware('housekeeping.access:manage_emulator_texts')->name('texts.update');
        Route::middleware('housekeeping.access:manage_wordfilter')->group(function () {
            Route::get('/wordfilter', [WordFilterController::class, 'index'])->name('wordfilter.index');
            Route::post('/wordfilter', [WordFilterController::class, 'store'])->name('wordfilter.store');
            // The entry rides in the body: entries such as discord.gg/ hold slashes.
            Route::put('/wordfilter', [WordFilterController::class, 'update'])->name('wordfilter.update');
            Route::delete('/wordfilter', [WordFilterController::class, 'destroy'])->name('wordfilter.destroy');
        });
        Route::middleware('housekeeping.access:edit_article')->group(function () {
            Route::get('/news', [NewsController::class, 'index'])->name('news.index');
            Route::post('/news', [NewsController::class, 'store'])->name('news.store');
            Route::patch('/news/{id}', [NewsController::class, 'update'])->whereNumber('id')->name('news.update');
            Route::delete('/news/{id}', [NewsController::class, 'destroy'])->whereNumber('id')->name('news.destroy');
        });
        Route::get('/website', [WebsiteController::class, 'index'])->name('website.index');
        // Each type checks its own capability (WebsiteController::PERMISSIONS).
        Route::post('/website/{type}', [WebsiteController::class, 'store'])->whereIn('type', WebsiteController::TYPES)->name('website.store');
        Route::put('/website/{type}/{id}', [WebsiteController::class, 'update'])->whereIn('type', WebsiteController::TYPES)->whereNumber('id')->name('website.update');
        Route::delete('/website/{type}/{id}', [WebsiteController::class, 'destroy'])->whereIn('type', WebsiteController::TYPES)->whereNumber('id')->name('website.destroy');

        // Logs
        Route::middleware('housekeeping.access:view_activity_logs')->group(function () {
            Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
            Route::get('/logs/counts', [LogController::class, 'counts'])->name('logs.counts');
            Route::get('/logs/export', [LogController::class, 'export'])->name('logs.export');
            Route::get('/views', [SavedViewController::class, 'index'])->name('views.index');
            Route::post('/views', [SavedViewController::class, 'store'])->name('views.store');
            Route::put('/views/{id}', [SavedViewController::class, 'update'])->whereNumber('id')->name('views.update');
            Route::delete('/views/{id}', [SavedViewController::class, 'destroy'])->whereNumber('id')->name('views.destroy');
            Route::get('/audit', AuditController::class)->name('audit');
        });

        // Admin
        Route::middleware('housekeeping.access:manage_housekeeping_permissions')->group(function () {
            Route::get('/access', [AccessController::class, 'index'])->name('access.index');
            Route::put('/access', [AccessController::class, 'update'])->name('access.update');
        });
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/{id}', [StaffController::class, 'show'])->whereNumber('id')->name('staff.show');
        Route::put('/staff/{id}', [StaffController::class, 'update'])->whereNumber('id')->middleware('housekeeping.access:manage_housekeeping_permissions')->name('staff.update');
        Route::middleware('housekeeping.access:manage_website_settings')->group(function () {
            Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
            Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
            Route::get('/settings/changes', [SettingController::class, 'changes'])->name('settings.changes');
        });
        Route::middleware('housekeeping.access:manage_emulator_settings')->group(function () {
            Route::get('/emulator-settings', [EmulatorSettingController::class, 'index'])->name('emulator-settings.index');
            Route::put('/emulator-settings', [EmulatorSettingController::class, 'update'])->name('emulator-settings.update');
        });
    });

    // Everything else under /housekeeping is the app; the React router reads
    // the path. `api` is excluded so an unknown API path is a JSON 404.
    Route::get('/{any?}', HousekeepingController::class)
        ->where('any', '^(?!api(/|$)).*$')
        ->name('housekeeping.app');
});
