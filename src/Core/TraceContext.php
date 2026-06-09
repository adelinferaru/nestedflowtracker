<?php

namespace AdelinFeraru\NestedFlowTracker\Core;

/**
 * A W3C Trace Context (`traceparent`) value: version 00, a 32-hex trace id, a
 * 16-hex parent (span) id, and a sampled flag. Our trace ids are already 32-hex,
 * so they map onto the W3C trace id directly.
 *
 * @see https://www.w3.org/TR/trace-context/
 */
class TraceContext
{
    private const ZERO_TRACE = '00000000000000000000000000000000';
    private const ZERO_PARENT = '0000000000000000';

    public function __construct(
        public readonly string $traceId,
        public readonly string $parentId,
        public readonly bool $sampled = true,
    ) {
    }

    /**
     * Parse a traceparent header, or return null if it is missing/malformed.
     */
    public static function parse(?string $header): ?self
    {
        if ($header === null) {
            return null;
        }

        if (! preg_match('/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/', strtolower(trim($header)), $m)) {
            return null;
        }

        if ($m[1] === self::ZERO_TRACE || $m[2] === self::ZERO_PARENT) {
            return null;
        }

        // Only bit 0 of the flags byte is "sampled" — higher bits (e.g. the
        // level-2 random-trace-id flag, 0x02) must not read as sampled.
        return new self($m[1], $m[2], (hexdec($m[3]) & 0x01) === 0x01);
    }

    /**
     * Build the traceparent header value.
     */
    public function toHeader(): string
    {
        return sprintf('00-%s-%s-%s', $this->traceId, $this->parentId, $this->sampled ? '01' : '00');
    }

    /**
     * Derive a 16-hex span id from a span's primary key (or a random one).
     */
    public static function spanId(int|string|null $id): string
    {
        if ($id === null) {
            return bin2hex(random_bytes(8));
        }

        $id = (string) $id;

        // Already a 16-hex span id — use it as-is.
        if (preg_match('/^[0-9a-f]{16}$/i', $id)) {
            return strtolower($id);
        }

        // A positive integer key hex-encodes directly. Anything else (uuid/ulid
        // keys, zero) hashes to a stable id instead of (int)-casting into the
        // W3C-invalid all-zero id or a truncated leading-digits value.
        if (ctype_digit($id) && $id !== '0') {
            return str_pad(substr(dechex((int) $id), 0, 16), 16, '0', STR_PAD_LEFT);
        }

        return substr(hash('sha256', $id), 0, 16);
    }
}
