<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Otel;

/**
 * Configuration for {@see OtelExporter}: where to ship spans and what to tag them with.
 *
 * HTTP timeout is intentionally not here — set it on the PSR-18 client when you
 * construct it; PSR-18 has no transport-level timeout knob.
 */
final readonly class OtelExporterConfig
{
    /**
     * @param array<string, string> $headers Extra headers (e.g. auth) added to every export.
     */
    public function __construct(
        public string $endpoint,
        public array $headers = [],
        public string $serviceName = 'app',
    ) {
    }
}
