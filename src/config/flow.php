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
];
