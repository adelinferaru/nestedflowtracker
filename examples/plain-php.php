<?php

/**
 * NestedFlowTracker without Laravel — a complete, runnable round trip:
 * trace a flow with the framework-agnostic Core, store it in SQLite via the
 * plain-PDO driver, then read the tree back with ordinary SQL.
 *
 *   composer install
 *   php examples/plain-php.php
 *
 * The Core depends only on PSR contracts: PSR-14 for events (a no-op
 * dispatcher is enough if you don't care about them) and a SpanDriver for
 * storage. No container, no framework.
 */

require __DIR__ . '/../vendor/autoload.php';

use AdelinFeraru\NestedFlowTracker\Core\Drivers\BufferedPdoDriver;
use AdelinFeraru\NestedFlowTracker\Core\Drivers\PdoSchema;
use AdelinFeraru\NestedFlowTracker\Core\FlowConfig;
use AdelinFeraru\NestedFlowTracker\Core\FlowTracker;
use Psr\EventDispatcher\EventDispatcherInterface;

// ── 1. Wire the tracker ────────────────────────────────────────────────────

$pdo = new PDO('sqlite::memory:'); // use a file path (sqlite:flows.sqlite) to persist
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
PdoSchema::create($pdo); // provisions the flow_spans table (sqlite / mysql / pgsql)

$flow = new FlowTracker(
    new FlowConfig(enabled: true, component: 'orders'),
    new class implements EventDispatcherInterface {
        public function dispatch(object $event): object
        {
            return $event; // plug in any PSR-14 dispatcher to react to SpanStarted/SpanFinished
        }
    },
    new BufferedPdoDriver($pdo), // one bulk INSERT per flow; PdoDriver writes per-span
);

// ── 2. Trace a flow ────────────────────────────────────────────────────────

$flow->span('checkout', function () use ($flow) {
    $flow->span('charge card', function ($span) {
        usleep(40_000);
        $span->context = ['gateway' => 'stripe'];
    });

    $flow->span('reserve stock', fn () => usleep(12_000));

    try {
        $flow->span('send confirmation email', function () {
            usleep(8_000);
            throw new RuntimeException('SMTP connection refused');
        });
    } catch (RuntimeException) {
        // span() marked the span failed (with the exception in `result`) and rethrew;
        // the checkout continues without the email.
    }

    return 'order-1042';
});

// ── 3. Read the tree back — plain SQL, no library code needed ─────────────

$spans = $pdo->query(
    "SELECT span_id, parent_span_id, name, status, duration
       FROM flow_spans ORDER BY started_at"
)->fetchAll(PDO::FETCH_ASSOC);

$children = [];
foreach ($spans as $span) {
    $children[$span['parent_span_id']][] = $span;
}

$print = function (array $span, string $indent) use (&$print, $children): void {
    printf(
        "%s%-28s %7.1f ms  %s\n",
        $indent,
        $span['name'],
        $span['duration'] * 1000,
        $span['status'] === 'failed' ? '⚠ failed' : ''
    );
    foreach ($children[$span['span_id']] ?? [] as $child) {
        $print($child, $indent . '   ');
    }
};

foreach ($children[null] ?? [] as $root) {
    $print($root, '');
}
