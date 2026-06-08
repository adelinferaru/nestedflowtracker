<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Span;
use DateTimeImmutable;
use PDO;

/**
 * Buffered variant of {@see PdoDriver}: holds every span in memory and writes
 * the whole flow in batched multi-row inserts when the root span closes.
 *
 * Trade-off: spans are not persisted until the flow completes; a crash mid-flow
 * loses everything in the buffer.
 */
class BufferedPdoDriver implements SpanDriver
{
    /**
     * Spans per INSERT batch. With 14 columns/row that stays well under SQLite's
     * default 999-placeholder limit and Postgres/MySQL's 65535 cap, so a single
     * flow with thousands of spans flushes in a few statements instead of throwing
     * "too many SQL variables".
     */
    private const BATCH_SIZE = 60;

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
            $this->writeBuffer();
        }
    }

    public function flush(): void
    {
        // Drop the buffer without writing — the surrounding flow is being abandoned.
        $this->buffer = [];
    }

    private function writeBuffer(): void
    {
        if ($this->buffer === []) {
            return;
        }

        foreach (array_chunk($this->buffer, self::BATCH_SIZE) as $batch) {
            $this->writeBatch($batch);
        }

        $this->buffer = [];
    }

    /**
     * @param list<Span> $batch
     */
    private function writeBatch(array $batch): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $columns = array_merge(array_keys($batch[0]->toRow()), ['created_at', 'updated_at']);

        $rowPlaceholders = '(' . str_repeat('?, ', count($columns) - 1) . '?)';
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES "
            . implode(', ', array_fill(0, count($batch), $rowPlaceholders));

        $values = [];
        foreach ($batch as $span) {
            array_push($values, ...array_values($span->toRow()));
            $values[] = $now;
            $values[] = $now;
        }

        $this->pdo->prepare($sql)->execute($values);
    }
}
