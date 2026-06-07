<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Span;
use DateTimeImmutable;
use PDO;

/**
 * Buffered variant of {@see PdoDriver}: holds every span in memory and writes
 * the whole flow in a single multi-row insert when the root span closes.
 * One write per flow instead of two per span.
 *
 * Trade-off: spans are not persisted until the flow completes; a crash mid-flow
 * loses everything in the buffer.
 */
class BufferedPdoDriver implements SpanDriver
{
    /** @var list<Span> */
    private array $buffer = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'flow_spans',
    ) {
    }

    public function opening(Span $span, ?Span $parent): void
    {
    }

    public function closing(Span $span): void
    {
        $this->buffer[] = $span;

        if ($span->parent_span_id === null) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $columns = ['trace_id', 'span_id', 'parent_span_id', 'name', 'component', 'user_id',
            'status', 'message', 'duration', 'started_at', 'context', 'result', 'created_at', 'updated_at'];

        $placeholders = [];
        $values = [];
        foreach ($this->buffer as $i => $span) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
                $key = "{$column}_{$i}";
                $rowPlaceholders[] = ":{$key}";
                $values[$key] = match ($column) {
                    'status' => $span->status->value,
                    'context' => $span->context !== null ? json_encode($span->context) : null,
                    'result' => $span->result !== null ? json_encode($span->result) : null,
                    'created_at', 'updated_at' => $now,
                    default => $span->{$column} ?? null,
                };
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES "
            . implode(', ', $placeholders);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        $this->buffer = [];
    }
}
