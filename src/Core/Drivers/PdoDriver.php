<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Span;
use DateTimeImmutable;
use PDO;

/**
 * Framework-agnostic database driver. Inserts each span on opening() and updates
 * it on closing(). The tree is reconstructed from parent_span_id at read time;
 * there's no nested-set bookkeeping.
 *
 * Pass any PDO connection (sqlite, mysql, pgsql). For the schema, see
 * {@see PdoSchema::create()} or your own migration.
 */
class PdoDriver implements SpanDriver
{
    /** @var array<string, int|string> Persisted row ids keyed by 16-hex span_id. */
    private array $byId = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'flow_spans',
    ) {
    }

    public function opening(Span $span, ?Span $parent): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = "INSERT INTO {$this->table}
            (trace_id, span_id, parent_span_id, name, component, user_id, status,
             message, duration, started_at, context, result, created_at, updated_at)
            VALUES
            (:trace_id, :span_id, :parent_span_id, :name, :component, :user_id, :status,
             :message, :duration, :started_at, :context, :result, :created_at, :updated_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'trace_id' => $span->trace_id,
            'span_id' => $span->span_id,
            'parent_span_id' => $span->parent_span_id,
            'name' => $span->name,
            'component' => $span->component,
            'user_id' => $span->user_id,
            'status' => $span->status->value,
            'message' => $span->message,
            'duration' => $span->duration,
            'started_at' => $span->started_at,
            'context' => $span->context !== null ? json_encode($span->context) : null,
            'result' => $span->result !== null ? json_encode($span->result) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($span->span_id !== null) {
            $this->byId[$span->span_id] = $this->pdo->lastInsertId();
        }
    }

    public function closing(Span $span): void
    {
        $id = $span->span_id !== null ? ($this->byId[$span->span_id] ?? null) : null;
        if ($id === null) {
            return;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = "UPDATE {$this->table}
            SET status = :status, message = :message, duration = :duration,
                context = :context, result = :result, updated_at = :updated_at
            WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'status' => $span->status->value,
            'message' => $span->message,
            'duration' => $span->duration,
            'context' => $span->context !== null ? json_encode($span->context) : null,
            'result' => $span->result !== null ? json_encode($span->result) : null,
            'updated_at' => $now,
            'id' => $id,
        ]);

        if ($span->parent_span_id === null) {
            // Release per-flow state once the root closes.
            $this->byId = [];
        }
    }
}
