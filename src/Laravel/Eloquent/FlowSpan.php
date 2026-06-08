<?php

namespace AdelinFeraru\NestedFlowTracker\Laravel\Eloquent;

use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * A single timed span. Spans share a trace_id and link via parent_span_id; the
 * tree is reconstructed at read time from those two columns.
 *
 * @property int $id
 * @property string $trace_id
 * @property string|null $span_id 16-hex W3C/OpenTelemetry span id.
 * @property string|null $parent_span_id 16-hex span id of the enclosing span.
 * @property string $name
 * @property string $component
 * @property int|string|null $user_id
 * @property SpanStatus $status
 * @property string|null $message
 * @property float|null $duration Seconds elapsed between start and end.
 * @property string|null $started_at Unix seconds (with microseconds) when the span opened.
 * @property array<string, mixed>|null $context
 * @property array<string, mixed>|null $result
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class FlowSpan extends Model
{
    protected $table = 'flow_spans';

    protected $guarded = [];

    /**
     * Declared as a property (not the casts() method) so casting works on
     * Laravel 10 as well as 11/12/13 — casts() is Laravel 11+.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => SpanStatus::class,
        'duration' => 'float',
        'context' => 'array',
        'result' => 'array',
    ];

    /**
     * Use the configured flow connection (falling back to the default) so writes
     * and viewer reads always target the same database.
     */
    public function getConnectionName(): ?string
    {
        return config('flow.connection') ?? $this->connection;
    }
}
