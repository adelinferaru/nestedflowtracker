<?php

namespace AdelinFeraru\NestedFlowTracker;

use AdelinFeraru\NestedFlowTracker\Bridge\PsrEventDispatcher;
use AdelinFeraru\NestedFlowTracker\Console\BenchmarkCommand;
use AdelinFeraru\NestedFlowTracker\Console\PruneCommand;
use AdelinFeraru\NestedFlowTracker\Console\ShowFlowCommand;
use AdelinFeraru\NestedFlowTracker\Core\Drivers\LogDriver;
use AdelinFeraru\NestedFlowTracker\Core\Drivers\NullDriver;
use AdelinFeraru\NestedFlowTracker\Core\Drivers\SpanDriver;
use AdelinFeraru\NestedFlowTracker\Core\FlowConfig;
use AdelinFeraru\NestedFlowTracker\Core\Otel\OtelExporter;
use AdelinFeraru\NestedFlowTracker\Core\Otel\OtelExporterConfig;
use AdelinFeraru\NestedFlowTracker\Drivers\BufferedDatabaseDriver;
use AdelinFeraru\NestedFlowTracker\Drivers\DatabaseDriver;
use AdelinFeraru\NestedFlowTracker\Drivers\OtelDriver;
use AdelinFeraru\NestedFlowTracker\Events\SpanFinished;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use AdelinFeraru\NestedFlowTracker\Http\Controllers\FlowApiController;
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
use Illuminate\Support\Facades\Log;
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
                'log' => new LogDriver(Log::channel($app['config']->get('flow.log.channel'))),
                'null' => new NullDriver(),
                'otel' => new OtelDriver($app->make(OtelExporter::class)),
                default => $app['config']->get('flow.buffer')
                    ? new BufferedDatabaseDriver()
                    : new DatabaseDriver(),
            };
        });

        // The Core OTLP exporter depends only on PSR-18/PSR-17. Default to Guzzle
        // (already in our require set), but bind the interfaces independently so
        // tests or downstream apps can substitute their own implementations.
        $this->app->bindIf(ClientInterface::class, function ($app) {
            return new GuzzleClient([
                'timeout' => (int) $app['config']->get('flow.otel.timeout', 5),
            ]);
        });
        $this->app->singletonIf(RequestFactoryInterface::class, HttpFactory::class);
        $this->app->singletonIf(StreamFactoryInterface::class, HttpFactory::class);

        $this->app->bind(OtelExporter::class, function ($app) {
            $config = $app['config'];
            /** @var array<string, string> $headers */
            $headers = $config->get('flow.otel.headers') ?: [];

            return new OtelExporter(
                new OtelExporterConfig(
                    endpoint: (string) $config->get('flow.otel.endpoint', ''),
                    headers: $headers,
                    serviceName: (string) $config->get('flow.component', 'app'),
                ),
                $app->make(ClientInterface::class),
                $app->make(RequestFactoryInterface::class),
                $app->make(StreamFactoryInterface::class),
            );
        });

        // Scoped so each HTTP request / queued job gets a fresh tracker (state is
        // flushed between them under Octane). FlowConfig is built from config('flow.*');
        // Laravel's dispatcher is adapted to PSR-14 (what FlowTracker depends on) so
        // the core stays framework-agnostic.
        $this->app->scoped(FlowTracker::class, function ($app) {
            return new FlowTracker(
                new FlowConfig(
                    enabled: (bool) $app['config']->get('flow.enabled', true),
                    component: (string) $app['config']->get('flow.component', 'app'),
                ),
                new PsrEventDispatcher($app['events']),
                $app->make(SpanDriver::class),
            );
        });
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
                BenchmarkCommand::class,
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
            Route::get('/api/flows', [FlowApiController::class, 'index'])->name('flow.api.index');
            Route::get('/api/flows/{trace}', [FlowApiController::class, 'show'])->name('flow.api.show');
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
            if ($event->span->parent_span_id !== null) {
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
