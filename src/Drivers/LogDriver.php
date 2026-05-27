<?php

namespace AdelinFeraru\NestedFlowTracker\Drivers;

use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Log;

/**
 * Writes each finished span as a structured log line. No database — so the viewer
 * and the artisan commands don't apply.
 */
class LogDriver implements SpanDriver
{
    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function opening(FlowSpan $span, ?FlowSpan $parent): void
    {
    }

    public function closing(FlowSpan $span): void
    {
        $channel = $this->config->get('flow.log.channel');

        Log::channel($channel)->info('flow.span', [
            'trace_id' => $span->trace_id,
            'span_id' => $span->span_id,
            'parent_span_id' => $span->parent_span_id,
            'name' => $span->name,
            'component' => $span->component,
            'status' => $span->status->value,
            'duration' => $span->duration,
            'context' => $span->context,
            'result' => $span->result,
        ]);
    }
}
