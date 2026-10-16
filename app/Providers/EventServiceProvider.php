<?php

namespace App\Providers;

use App\Listeners\RecordLoginAttempt;
use App\Models\Community\Staff\WebsiteTeam;
use App\Models\Game\Permission;
use App\Models\User;
use App\Observers\CommunityCacheObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        // pixelrp: the log in history under Settings > Sessions.
        Login::class => [
            RecordLoginAttempt::class,
        ],
        Failed::class => [
            RecordLoginAttempt::class,
        ],
    ];

    protected $observers = [
        User::class => [UserObserver::class, CommunityCacheObserver::class],
        Permission::class => [CommunityCacheObserver::class],
        WebsiteTeam::class => [CommunityCacheObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void {}

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
