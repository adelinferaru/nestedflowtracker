<?php

namespace AdelinFeraru\NestedFlowTracker;

use AdelinFeraru\NestedFlowTracker\Http\Middleware\TrackRequest;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
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

        $config = $this->app['config'];

        if ($config->get('flow.auto.http')) {
            $this->registerHttpInstrumentation();
        }

        if ($config->get('flow.auto.queue')) {
            $this->registerQueueInstrumentation();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/flow.php' => config_path('flow.php'),
            ], 'flow-config');

            $this->publishes([
                __DIR__ . '/migrations/' => database_path('migrations'),
            ], 'flow-migrations');
        }
    }

    /**
     * Add the request-tracking middleware to the web and api groups.
     *
     * Appended via the HTTP kernel (not the router) so it survives the kernel
     * syncing its own default groups to the router on the first request.
     */
    private function registerHttpInstrumentation(): void
    {
        if (! $this->app->bound(Kernel::class)) {
            return;
        }

        /** @var Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);

        // appendMiddlewareToGroup lives on the Foundation HTTP kernel, not the contract.
        if (! method_exists($kernel, 'appendMiddlewareToGroup')) {
            return;
        }

        foreach (['web', 'api'] as $group) {
            $kernel->appendMiddlewareToGroup($group, TrackRequest::class);
        }
    }

    /**
     * Open/close a root span around each queued job via the queue events.
     */
    private function registerQueueInstrumentation(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app['events'];

        $events->listen(JobProcessing::class, function (JobProcessing $event): void {
            $flow = $this->app->make(FlowTracker::class);
            // Each job is its own flow, isolated from any previous job on this worker.
            $flow->flush();
            $flow->start('job: ' . $event->job->resolveName(), [
                'root' => true,
                'context' => [
                    'connection' => $event->connectionName,
                    'queue' => $event->job->getQueue(),
                ],
            ]);
        });

        $events->listen(JobProcessed::class, function (): void {
            $this->app->make(FlowTracker::class)->end();
        });

        $events->listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event): void {
            $flow = $this->app->make(FlowTracker::class);
            $flow->fail($event->exception);
            $flow->end();
        });
    }
}
