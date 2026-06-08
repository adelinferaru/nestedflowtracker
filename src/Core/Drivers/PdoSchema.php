<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Drivers;

use PDO;
use RuntimeException;

/**
 * Creates the flow_spans table for the PDO drivers. Use it in your own migration
 * or call it once during bootstrap if you don't have a migration tool. Supported
 * driver names: sqlite, mysql, pgsql.
 *
 * Schema is the lean variant — no nested-set columns (parent_id / _lft / _rgt);
 * the tree is built from parent_span_id, which works for every Core driver.
 */
final class PdoSchema
{
    public static function create(PDO $pdo, string $table = 'flow_spans'): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $statements = match ($driver) {
            'sqlite' => self::sqlite($table),
            'mysql' => self::mysql($table),
            'pgsql' => self::pgsql($table),
            default => throw new RuntimeException("PdoSchema: unsupported PDO driver '{$driver}'."),
        };

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
    }

    public static function dropIfExists(PDO $pdo, string $table = 'flow_spans'): void
    {
        $pdo->exec("DROP TABLE IF EXISTS {$table}");
    }

    /** @return list<string> */
    private static function sqlite(string $table): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS {$table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trace_id VARCHAR(32) NOT NULL,
                span_id VARCHAR(16),
                parent_span_id VARCHAR(16),
                name VARCHAR(255) NOT NULL,
                component VARCHAR(255) NOT NULL,
                user_id VARCHAR(255),
                status VARCHAR(16) NOT NULL DEFAULT 'ok',
                message TEXT,
                duration DECIMAL(12,6),
                started_at DECIMAL(20,6),
                context TEXT,
                result TEXT,
                created_at DATETIME,
                updated_at DATETIME
            )",
            "CREATE INDEX IF NOT EXISTS {$table}_trace_id_idx ON {$table}(trace_id)",
            "CREATE INDEX IF NOT EXISTS {$table}_span_id_idx ON {$table}(span_id)",
            "CREATE INDEX IF NOT EXISTS {$table}_created_at_idx ON {$table}(created_at)",
        ];
    }

    /** @return list<string> */
    private static function mysql(string $table): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                trace_id VARCHAR(32) NOT NULL,
                span_id VARCHAR(16),
                parent_span_id VARCHAR(16),
                name VARCHAR(255) NOT NULL,
                component VARCHAR(255) NOT NULL,
                user_id VARCHAR(255),
                status VARCHAR(16) NOT NULL DEFAULT 'ok',
                message TEXT,
                duration DECIMAL(12,6),
                started_at DECIMAL(20,6),
                context JSON,
                result JSON,
                created_at DATETIME,
                updated_at DATETIME,
                INDEX {$table}_trace_id_idx (trace_id),
                INDEX {$table}_span_id_idx (span_id),
                INDEX {$table}_created_at_idx (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }

    /** @return list<string> */
    private static function pgsql(string $table): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGSERIAL PRIMARY KEY,
                trace_id VARCHAR(32) NOT NULL,
                span_id VARCHAR(16),
                parent_span_id VARCHAR(16),
                name VARCHAR(255) NOT NULL,
                component VARCHAR(255) NOT NULL,
                user_id VARCHAR(255),
                status VARCHAR(16) NOT NULL DEFAULT 'ok',
                message TEXT,
                duration NUMERIC(12,6),
                started_at NUMERIC(20,6),
                context JSONB,
                result JSONB,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )",
            "CREATE INDEX IF NOT EXISTS {$table}_trace_id_idx ON {$table}(trace_id)",
            "CREATE INDEX IF NOT EXISTS {$table}_span_id_idx ON {$table}(span_id)",
            "CREATE INDEX IF NOT EXISTS {$table}_created_at_idx ON {$table}(created_at)",
        ];
    }
}
