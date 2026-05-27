<?php

namespace AdelinFeraru\NestedFlowTracker\Enums;

enum SpanStatus: string
{
    /** The span is open and still timing. */
    case Running = 'running';

    /** The span completed normally. */
    case Ok = 'ok';

    /** The span ended because of an exception/error. */
    case Failed = 'failed';
}
