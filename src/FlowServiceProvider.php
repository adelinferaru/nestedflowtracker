<?php

namespace AdelinFeraru\NestedFlowTracker;

use Illuminate\Support\ServiceProvider;

class FlowServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/flow.php', 'flow');

        // Scoped so each HTTP request / queued job gets a fresh tracker (state is
        // flushed between them under Octane). Config + event dispatcher are autowired.
        $this->app->scoped(FlowTracker::class);
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/flow.php' => config_path('flow.php'),
            ], 'flow-config');

            $this->publishes([
                __DIR__ . '/migrations/' => database_path('migrations'),
            ], 'flow-migrations');
        }
    }
}
