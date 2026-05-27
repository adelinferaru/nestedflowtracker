<?php

namespace AdelinFeraru\NestedFlowTracker;

use AdelinFeraru\NestedFlowTracker\Console\PruneCommand;
use AdelinFeraru\NestedFlowTracker\Console\ShowFlowCommand;
use AdelinFeraru\NestedFlowTracker\Drivers\DatabaseDriver;
use AdelinFeraru\NestedFlowTracker\Drivers\LogDriver;
use AdelinFeraru\NestedFlowTracker\Drivers\NullDriver;
use AdelinFeraru\NestedFlowTracker\Drivers\OtelDriver;
use AdelinFeraru\NestedFlowTracker\Drivers\SpanDriver;
use AdelinFeraru\NestedFlowTracker\Events\SpanFinished;
use AdelinFeraru\NestedFlowTracker\Otel\OtelExporter;
use AdelinFeraru\NestedFlowTracker\Http\Controllers\FlowViewerController;
use AdelinFeraru\NestedFlowTracker\Http\Middleware\Authorize;
use AdelinFeraru\NestedFlowTracker\Http\Middleware\TrackRequest;
use AdelinFeraru\NestedFlowTracker\Otel\ExportTrace;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FlowServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/flow.php', 'flow');

        // Resolve the active storage driver from config. Scoped so the otel driver's
        // in-memory buffer is per request/job.
        $this->app->scoped(SpanDriver::class, function ($app) {
            return match ($app['config']->get('flow.driver', 'database')) {
                'log' => new LogDriver($app['config']),
                'null' => new NullDriver(),
                'otel' => new OtelDriver($app->make(OtelExporter::class)),
                default => new DatabaseDriver(),
            };
        });

        // Scoped so each HTTP request / queued job gets a fresh tracker (state is
        // flushed between them under Octane). Config + dispatcher + driver are autowired.
        $this->app->scoped(FlowTracker::class);
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'flow');
        $this->registerHttpClientMacro();

        $config = $this->app['config'];

        if ($config->get('flow.auto.http')) {
            $this->registerHttpInstrumentation();
        }

        if ($config->get('flow.auto.queue')) {
            $this->registerQueueInstrumentation();
        }

        if ($config->get('flow.viewer.enabled')) {
            $this->registerViewer();
        }

        if ($config->get('flow.otel.enabled') && $config->get('flow.otel.endpoint')) {
            $this->registerOtelExport();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/flow.php' => config_path('flow.php'),
            ], 'flow-config');

            $this->publishes([
                __DIR__ . '/migrations/' => database_path('migrations'),
            ], 'flow-migrations');

            $this->publishes([
                __DIR__ . '/resources/views' => base_path('resources/views/vendor/flow'),
            ], 'flow-views');

            $this->commands([
                PruneCommand::class,
                ShowFlowCommand::class,
            ]);
        }
    }

    /**
     * Add `Http::withFlowTrace()` to inject the current flow's W3C traceparent
     * header onto an outbound request.
     */
    private function registerHttpClientMacro(): void
    {
        $container = $this->app;

        PendingRequest::macro('withFlowTrace', function () use ($container) {
            /** @var PendingRequest $this */
            $flow = $container->make(FlowTracker::class);
            $traceId = $flow->traceId();

            if ($traceId !== null) {
                $current = $flow->currentSpan();
                $spanId = ($current !== null ? $current->span_id : null) ?? TraceContext::spanId(null);
                $this->withHeaders(['traceparent' => (new TraceContext($traceId, $spanId))->toHeader()]);
            }

            return $this;
        });
    }

    /**
     * Register the viewer routes and views.
     */
    private function registerViewer(): void
    {
        $config = $this->app['config'];

        Route::group([
            'prefix' => $config->get('flow.viewer.path', 'flow'),
            'middleware' => array_merge(
                (array) $config->get('flow.viewer.middleware', ['web']),
                [Authorize::class],
            ),
        ], function () {
            Route::get('/', [FlowViewerController::class, 'index'])->name('flow.index');
            Route::get('/{trace}', [FlowViewerController::class, 'show'])->name('flow.show');
        });
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
            if ($event->job->resolveName() === ExportTrace::class) {
                return; // Don't trace our own exporter.
            }
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

        $events->listen(JobProcessed::class, function (JobProcessed $event): void {
            if ($event->job->resolveName() === ExportTrace::class) {
                return;
            }
            $this->app->make(FlowTracker::class)->end();
        });

        $events->listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event): void {
            if ($event->job->resolveName() === ExportTrace::class) {
                return;
            }
            $flow = $this->app->make(FlowTracker::class);
            $flow->fail($event->exception);
            $flow->end();
        });
    }

    /**
     * Export each completed flow (when its root span closes) to the OTLP collector.
     */
    private function registerOtelExport(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app['events'];

        $queue = $this->app['config']->get('flow.otel.queue');

        $events->listen(SpanFinished::class, function (SpanFinished $event) use ($queue): void {
            // A root span closing means the whole flow is complete.
            if ($event->span->parent_id !== null) {
                return;
            }

            $job = new ExportTrace($event->span->trace_id);
            if ($queue !== null) {
                $job->onQueue((string) $queue);
            }

            dispatch($job);
        });
    }
}
