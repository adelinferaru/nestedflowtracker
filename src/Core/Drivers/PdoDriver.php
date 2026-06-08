<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Span;
use DateTimeImmutable;
use PDO;
use PDOStatement;

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
    private ?PDOStatement $insertStmt = null;
    private ?PDOStatement $updateStmt = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'flow_spans',
    ) {
    }

    public function opening(Span $span, ?Span $parent): void
    {
        if ($this->insertStmt === null) {
            $this->insertStmt = $this->pdo->prepare(
                "INSERT INTO {$this->table}
                (trace_id, span_id, parent_span_id, name, component, user_id, status,
                 message, duration, started_at, context, result, created_at, updated_at)
                VALUES
                (:trace_id, :span_id, :parent_span_id, :name, :component, :user_id, :status,
                 :message, :duration, :started_at, :context, :result, :created_at, :updated_at)"
            );
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->insertStmt->execute([
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
    }

    public function closing(Span $span): void
    {
        // span_id is the canonical identity; PdoSchema indexes it. UPDATE WHERE span_id
        // avoids needing pgsql's sequence name for lastInsertId() and works for any driver.
        if ($span->span_id === null) {
            return;
        }

        if ($this->updateStmt === null) {
            $this->updateStmt = $this->pdo->prepare(
                "UPDATE {$this->table}
                SET status = :status, message = :message, duration = :duration,
                    context = :context, result = :result, updated_at = :updated_at
                WHERE span_id = :span_id"
            );
        }

        $this->updateStmt->execute([
            'status' => $span->status->value,
            'message' => $span->message,
            'duration' => $span->duration,
            'context' => $span->context !== null ? json_encode($span->context) : null,
            'result' => $span->result !== null ? json_encode($span->result) : null,
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'span_id' => $span->span_id,
        ]);
    }

    public function flush(): void
    {
        // The driver holds no per-flow state — prepared statements are per-connection.
    }
}
