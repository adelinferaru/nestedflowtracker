<?php

namespace AdelinFeraru\NestedFlowTracker;

use Illuminate\Support\ServiceProvider;

class NestedFlowTrackerServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/nestedflowtracker.php', 'nestedflowtracker');
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'adelinferaru');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'adelinferaru');
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {


        // Register the service the package provides.
        $this->app->singleton('nestedflowtracker', function ($app) {
            return new NestedFlowTracker;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['nestedflowtracker'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole()
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__ . '/config/nestedflowtracker.php' => config_path('nestedflowtracker.php'),
        ], 'nestedflowtracker.config');


        $this->loadMigrationsFrom(__DIR__ . '/migrations');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/adelinferaru'),
        ], 'nestedflowtracker.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/adelinferaru'),
        ], 'nestedflowtracker.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/adelinferaru'),
        ], 'nestedflowtracker.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
