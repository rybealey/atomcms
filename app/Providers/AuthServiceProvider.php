<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Roleplay\Bin;
use App\Models\Roleplay\ChargeType;
use App\Policies\ActivityPolicy;
use App\Policies\BinPolicy;
use App\Policies\ChargeTypePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        Activity::class => ActivityPolicy::class,
        Bin::class => BinPolicy::class,
        ChargeType::class => ChargeTypePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
