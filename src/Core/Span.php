<?php

namespace AdelinFeraru\NestedFlowTracker\Core;

use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;

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
     * Columns produced by {@see toRow()}, in the order toRow() returns them.
     * Drivers that need a positional column list (e.g. BufferedPdoDriver's
     * multi-row INSERT) read this constant to stay in lockstep with toRow().
     */
    public const COLUMNS = [
        'trace_id', 'span_id', 'parent_span_id', 'name', 'component', 'user_id',
        'status', 'message', 'duration', 'started_at', 'context', 'result',
    ];

    /**
     * Columns produced by {@see toRowMutable()} — the subset of {@see COLUMNS}
     * that can change between opening() and closing(). What drivers write in
     * `UPDATE … WHERE span_id = ?` on close.
     */
    public const MUTABLE_COLUMNS = ['status', 'message', 'duration', 'context', 'result'];

    /**
     * Insert-ready row mapping (status as string value, context/result as JSON
     * strings, no timestamp columns). Used by every persistence driver as the
     * single source of truth for the flow_spans column shape and for the
     * `status`/`context`/`result` serialization.
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'trace_id' => $this->trace_id,
            'span_id' => $this->span_id,
            'parent_span_id' => $this->parent_span_id,
            'name' => $this->name,
            'component' => $this->component,
            'user_id' => $this->user_id,
            'status' => $this->status->value,
            'message' => $this->message,
            'duration' => $this->duration,
            'started_at' => $this->started_at,
            'context' => self::encode($this->context),
            'result' => self::encode($this->result),
        ];
    }

    /**
     * JSON-encode a context/result payload without ever yielding `false` — invalid
     * UTF-8 is substituted and unencodable values (NAN, resources, recursion) are
     * dropped rather than turning the value into '' (which PDO would bind and a
     * MySQL JSON column would reject, failing the whole row).
     *
     * @param array<string, mixed>|null $value
     */
    private static function encode(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? null : $json;
    }

    /**
     * Subset of {@see toRow()} keyed by {@see MUTABLE_COLUMNS} — derived from
     * toRow() so the serialization of context/result stays in lockstep.
     *
     * @return array<string, mixed>
     */
    public function toRowMutable(): array
    {
        return array_intersect_key($this->toRow(), array_flip(self::MUTABLE_COLUMNS));
    }
}
