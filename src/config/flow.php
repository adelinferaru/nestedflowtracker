<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch. When false, spans become transparent no-ops: span() still
    | runs your callback and returns its value, but nothing is timed or stored.
    |
    */
    'enabled' => env('FLOW_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Component
    |--------------------------------------------------------------------------
    |
    | The name of this application/service, stored on every span. Useful when a
    | single flow (one trace_id) spans multiple applications.
    |
    */
    'component' => env('FLOW_COMPONENT', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Storage driver
    |--------------------------------------------------------------------------
    |
    | Where finished spans go:
    |   - database: store as a tree (enables the viewer + artisan commands).
    |   - log:      write each span as a structured log line (no database).
    |   - null:     discard (API stays on, nothing stored).
    |   - otel:     send straight to an OTLP/HTTP collector (no database).
    |
    | The viewer, `flow:show`/`flow:prune`, and the `flow.otel` export below are
    | database-only features.
    |
    */
    'driver' => env('FLOW_DRIVER', 'database'),

    /*
    | Buffer a whole flow in memory and bulk-insert it when the root span closes
    | (database driver only). Far fewer writes, but spans are not persisted until
    | the flow completes. Off by default.
    */
    'buffer' => env('FLOW_BUFFER', false),

    /*
    | Log channel used by the `log` driver (null = the default channel).
    */
    'log' => [
        'channel' => env('FLOW_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | The connection the flow_spans table lives on. Null uses the default
    | connection; set a named connection (defined in config/database.php) to
    | store spans in a separate database.
    |
    */
    'connection' => env('FLOW_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Automatic instrumentation
    |--------------------------------------------------------------------------
    |
    | Opt in to record spans with zero manual calls:
    |   - http:  a root span per HTTP request (added to the web + api groups).
    |   - queue: a root span per queued job.
    |
    | Both default to off so installing the package never silently writes spans;
    | flip them on once you've published the migration.
    |
    */
    'auto' => [
        'http' => env('FLOW_AUTO_HTTP', false),
        'queue' => env('FLOW_AUTO_QUEUE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Viewer
    |--------------------------------------------------------------------------
    |
    | A small built-in UI to browse recorded flows as timed trees.
    |
    |   - enabled:    register the viewer routes (off by default; it exposes data).
    |   - path:       URL prefix for the viewer.
    |   - middleware: middleware applied to the viewer routes.
    |
    | Access is allowed automatically in the local environment. In any other
    | environment you must define a `viewFlow` gate to grant access:
    |
    |   Gate::define('viewFlow', fn ($user) => $user->isAdmin());
    |
    */
    'viewer' => [
        'enabled' => env('FLOW_VIEWER', false),
        'path' => env('FLOW_VIEWER_PATH', 'flow'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry export (optional)
    |--------------------------------------------------------------------------
    |
    | Ship completed flows to an OTLP/HTTP collector (e.g. an OpenTelemetry
    | Collector, Jaeger, Grafana Tempo). When a root span closes, the whole
    | trace is exported on a queue. No SDK required — we POST OTLP-JSON.
    |
    |   - endpoint: collector base URL; spans go to {endpoint}/v1/traces.
    |   - headers:  extra headers (e.g. auth) for the collector request.
    |   - queue:    queue the export job runs on (null = default).
    |
    */
    'otel' => [
        'enabled' => env('FLOW_OTEL_ENABLED', false),
        'endpoint' => env('FLOW_OTEL_ENDPOINT'),
        'headers' => [],
        'timeout' => env('FLOW_OTEL_TIMEOUT', 5),
        'queue' => env('FLOW_OTEL_QUEUE'),
    ],
];
