<?php

namespace AdelinFeraru\NestedFlowTracker\Otel;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Queued export of one completed flow to the OTLP collector.
 */
class ExportTrace implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly string $traceId,
    ) {
    }

    public function handle(OtelExporter $exporter): void
    {
        $exporter->export($this->traceId);
    }
}
