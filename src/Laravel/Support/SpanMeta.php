<?php

namespace AdelinFeraru\NestedFlowTracker\Laravel\Support;

/**
 * Formats a span's stored metadata (the context/result arrays) as compact
 * `key=value` pairs. Shared by the viewer and flow:show so both surfaces
 * render the same shape.
 */
final class SpanMeta
{
    /**
     * Values longer than this are truncated with an ellipsis so one oversized
     * payload can't wreck a tree row or a terminal line.
     */
    private const MAX_VALUE_LENGTH = 120;

    /**
     * @param array<array-key, mixed>|null $data
     * @return list<string> `key=value` pairs, one per entry.
     */
    public static function pairs(?array $data): array
    {
        $pairs = [];
        foreach ($data ?? [] as $key => $value) {
            $pairs[] = $key . '=' . self::value($value);
        }

        return $pairs;
    }

    private static function value(mixed $value): string
    {
        $formatted = match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '?',
        };

        return mb_strlen($formatted) > self::MAX_VALUE_LENGTH
            ? mb_substr($formatted, 0, self::MAX_VALUE_LENGTH - 1) . '…'
            : $formatted;
    }
}
