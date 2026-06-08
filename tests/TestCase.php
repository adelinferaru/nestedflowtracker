<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Laravel\FlowServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // The tracker is a scoped binding, so testbench's per-test application
        // refresh already gives each test a clean instance — no manual reset needed.
        $this->artisan('migrate')->run();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FlowServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // The web middleware group (cookies/session) needs an app key.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('flow.enabled', true);
        $app['config']->set('flow.component', 'test-component');
        $app['config']->set('flow.connection', null);
    }
}
