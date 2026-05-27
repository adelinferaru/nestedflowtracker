<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\NestedFlowTrackerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run the package migration against the in-memory test database.
        $this->artisan('migrate')->run();
    }

    /**
     * Register the package's service provider.
     */
    protected function getPackageProviders($app): array
    {
        return [
            NestedFlowTrackerServiceProvider::class,
        ];
    }

    /**
     * Configure the test environment: an in-memory SQLite database and an
     * active flow tracker writing to the default connection.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('nestedflowtracker.flow_tracker_active', 1);
        $app['config']->set('nestedflowtracker.db_connection', 'default');
        $app['config']->set('nestedflowtracker.component', 'test-component');
    }
}
