<?php

namespace App\Providers;

use App\Models\DatabaseHost;
use App\Models\Egg;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Models\Webhook;
use App\Observers\AdminActivityObserver;
use App\Services\Activity\ActivityLogTargetableService;
use App\Services\Activity\AdminActivityLogService;
use Illuminate\Support\ServiceProvider;

class ActivityLogServiceProvider extends ServiceProvider
{
    /**
     * Registers the necessary activity logger singletons scoped to the individual
     * request instances.
     */
    public function register(): void
    {
        $this->app->scoped(ActivityLogTargetableService::class);
        $this->app->scoped(AdminActivityLogService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the admin activity observer for key models
        User::observe(AdminActivityObserver::class);
        Server::observe(AdminActivityObserver::class);
        Node::observe(AdminActivityObserver::class);
        Egg::observe(AdminActivityObserver::class);
        Role::observe(AdminActivityObserver::class);
        DatabaseHost::observe(AdminActivityObserver::class);
        Mount::observe(AdminActivityObserver::class);
        Webhook::observe(AdminActivityObserver::class);
    }
}
