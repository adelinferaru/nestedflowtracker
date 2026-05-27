<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\NestedFlowTracker;
use AdelinFeraru\NestedFlowTracker\NestedFlowTrackerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // NestedFlowTracker keeps process-global static state that survives the
        // per-test application refresh; reset it so each test starts clean.
        // (This global state is itself a Phase 3 refactor target.)
        $this->resetTrackerState();

        // Run the package migration against the in-memory test database.
        $this->artisan('migrate')->run();
    }

    /**
     * Reset NestedFlowTracker's static properties to their initial values.
     */
    protected function resetTrackerState(): void
    {
        $defaults = [
            'tracker_id' => null,
            'user_id' => null,
            'timers' => [],
            'tracks_queue' => [],
            'db_connection' => null,
        ];

        $reflection = new ReflectionClass(NestedFlowTracker::class);
        foreach ($defaults as $property => $value) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue(null, $value);
        }
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
