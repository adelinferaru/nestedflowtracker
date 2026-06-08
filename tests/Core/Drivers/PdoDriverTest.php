<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Core\Drivers;

use AdelinFeraru\NestedFlowTracker\Laravel\Bridge\PsrEventDispatcher;
use AdelinFeraru\NestedFlowTracker\Core\Drivers\PdoDriver;
use AdelinFeraru\NestedFlowTracker\Core\Drivers\PdoSchema;
use AdelinFeraru\NestedFlowTracker\Core\FlowConfig;
use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Core\FlowTracker;
use Illuminate\Events\Dispatcher;
use PDO;
use PHPUnit\Framework\TestCase;

class PdoDriverTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        PdoSchema::create($this->pdo);
    }

    public function test_span_round_trip_persists_status_and_duration(): void
    {
        $tracker = $this->trackerWith(new PdoDriver($this->pdo));

        $tracker->span('compute', fn () => null);

        $row = $this->pdo->query('SELECT * FROM flow_spans')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('compute', $row['name']);
        $this->assertSame(SpanStatus::Ok->value, $row['status']);
        $this->assertNotNull($row['duration']);
        $this->assertNotNull($row['span_id']);
    }

    public function test_nested_spans_share_a_trace_and_link_via_parent_span_id(): void
    {
        $tracker = $this->trackerWith(new PdoDriver($this->pdo));

        $tracker->span('root', function () use ($tracker) {
            $tracker->span('child', fn () => null);
        });

        $rows = $this->pdo->query('SELECT name, trace_id, parent_span_id, span_id FROM flow_spans ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);

        [$child, $root] = $rows[0]['name'] === 'child' ? [$rows[0], $rows[1]] : [$rows[1], $rows[0]];
        $this->assertSame($root['trace_id'], $child['trace_id']);
        $this->assertSame($root['span_id'], $child['parent_span_id']);
        $this->assertNull($root['parent_span_id']);
    }

    public function test_failed_span_records_exception_in_result(): void
    {
        $tracker = $this->trackerWith(new PdoDriver($this->pdo));

        try {
            $tracker->span('boom', function () {
                throw new \RuntimeException('kaboom');
            });
            $this->fail('Exception was not rethrown.');
        } catch (\RuntimeException) {
        }

        $row = $this->pdo->query('SELECT status, result FROM flow_spans')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(SpanStatus::Failed->value, $row['status']);
        $result = json_decode((string) $row['result'], true);
        $this->assertSame(\RuntimeException::class, $result['exception']);
        $this->assertSame('kaboom', $result['message']);
    }

    private function trackerWith(PdoDriver $driver): FlowTracker
    {
        return new FlowTracker(
            new FlowConfig(enabled: true, component: 'core-test'),
            new PsrEventDispatcher(new Dispatcher()),
            $driver,
        );
    }
}
