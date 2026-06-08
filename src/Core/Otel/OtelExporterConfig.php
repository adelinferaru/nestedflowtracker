<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Otel;

/**
 * Configuration for {@see OtelExporter}: where to ship spans and what to tag them with.
 *
 * HTTP timeout is intentionally not here — set it on the PSR-18 client when you
 * construct it; PSR-18 has no transport-level timeout knob.
 */
final class OtelExporterConfig
{
    /**
     * @param array<string, string> $headers Extra headers (e.g. auth) added to every export.
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly array $headers = [],
        public readonly string $serviceName = 'app',
    ) {
    }
}
