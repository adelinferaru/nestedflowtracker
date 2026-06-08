<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Span;
use Psr\Log\LoggerInterface;

/**
 * Writes each finished span as a structured log line through a PSR-3 logger.
 * No database — so the viewer and the artisan commands don't apply.
 *
 * Pass any PSR-3 logger (Monolog, Laravel's Log::channel(), etc.); the package's
 * service provider wires Laravel's channel automatically when `flow.driver = log`.
 */
class LogDriver implements SpanDriver
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function opening(Span $span, ?Span $parent): void
    {
    }

    public function closing(Span $span): void
    {
        $this->logger->info('flow.span', [
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

    public function flush(): void
    {
    }
}
