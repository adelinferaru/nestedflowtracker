<?php

return [
    /*
     * Specify the default component to use
     * */
    'component' => env('FLOW_TRACKER_COMPONENT', 'bridge'),

    /*
     * Specify witch database connection to use
     * */
    'db_connection' => env('FLOW_TRACKER_DB_CONNECTION', 'default')
];
