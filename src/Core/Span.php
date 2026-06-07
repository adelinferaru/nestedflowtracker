<?php

namespace AdelinFeraru\NestedFlowTracker\Core;

use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;

/**
 * A single timed span — the framework-agnostic shape that the tracker, the drivers,
 * the events, and the OTLP exporter all operate on. The Eloquent FlowSpan model is
 * a Laravel-only persistence wrapper around this same shape.
 *
 * Public properties intentionally mirror the flow_spans columns so drivers can map
 * them directly without per-field accessors.
 */
class Span
{
    public string $trace_id = '';

    /** 16-hex W3C/OpenTelemetry span id. */
    public ?string $span_id = null;

    /** 16-hex span id of the enclosing span; null on a root span. */
    public ?string $parent_span_id = null;

    public string $name = '';

    public string $component = '';

    public int|string|null $user_id = null;

    public SpanStatus $status = SpanStatus::Running;

    public ?string $message = null;

    /** Seconds elapsed between start and end (set on close). */
    public ?float $duration = null;

    /** Unix seconds (with microseconds) when the span opened, as a numeric string. */
    public ?string $started_at = null;

    /** @var array<string, mixed>|null */
    public ?array $context = null;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    /**
     * Relational parent FK — used by the Eloquent database driver to attach this
     * span to a known row id. Non-relational drivers ignore it; the canonical
     * parent linkage is `parent_span_id`.
     */
    public ?int $parent_id = null;
}
