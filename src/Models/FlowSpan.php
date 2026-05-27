<?php

namespace AdelinFeraru\NestedFlowTracker\Models;

use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;
use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

/**
 * A single timed span within a flow. Spans form a tree (nested set) and share a trace_id.
 *
 * @property int $id
 * @property string $trace_id
 * @property string $name
 * @property string $component
 * @property int|string|null $user_id
 * @property SpanStatus $status
 * @property string|null $message
 * @property float|null $duration Seconds elapsed between start and end.
 * @property array<string, mixed>|null $context
 * @property array<string, mixed>|null $result
 * @property int|null $parent_id
 * @property int $_lft
 * @property int $_rgt
 */
class FlowSpan extends Model
{
    use NodeTrait;

    protected $table = 'flow_spans';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SpanStatus::class,
            'duration' => 'float',
            'context' => 'array',
            'result' => 'array',
        ];
    }
}
