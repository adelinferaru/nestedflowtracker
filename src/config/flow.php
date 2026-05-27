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
];
