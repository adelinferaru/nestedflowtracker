<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Core\Drivers;

use AdelinFeraru\NestedFlowTracker\Laravel\Bridge\PsrEventDispatcher;
use AdelinFeraru\NestedFlowTracker\Core\Drivers\BufferedPdoDriver;
use AdelinFeraru\NestedFlowTracker\Core\Drivers\PdoSchema;
use AdelinFeraru\NestedFlowTracker\Core\FlowConfig;
use AdelinFeraru\NestedFlowTracker\Core\FlowTracker;
use Illuminate\Events\Dispatcher;
use PDO;
use PHPUnit\Framework\TestCase;

class BufferedPdoDriverTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        PdoSchema::create($this->pdo);
    }

    public function test_nothing_is_persisted_until_the_root_closes(): void
    {
        $driver = new BufferedPdoDriver($this->pdo);
        $tracker = $this->trackerWith($driver);

        $root = $tracker->start('root');
        $this->assertSame(0, $this->countRows());

        $tracker->start('child');
        $tracker->end();
        $this->assertSame(0, $this->countRows());

        $tracker->end();
        $this->assertSame(2, $this->countRows());
        unset($root);
    }

    public function test_whole_flow_is_written_in_one_pass_and_tree_links_via_parent_span_id(): void
    {
        $tracker = $this->trackerWith(new BufferedPdoDriver($this->pdo));

        $tracker->span('root', function () use ($tracker) {
            $tracker->span('child-a', fn () => null);
            $tracker->span('child-b', fn () => null);
        });

        $this->assertSame(3, $this->countRows());
        $rows = $this->pdo->query('SELECT name, parent_span_id, span_id FROM flow_spans')
            ->fetchAll(PDO::FETCH_ASSOC);
        $root = array_values(array_filter($rows, fn ($r) => $r['name'] === 'root'))[0];
        $childA = array_values(array_filter($rows, fn ($r) => $r['name'] === 'child-a'))[0];
        $childB = array_values(array_filter($rows, fn ($r) => $r['name'] === 'child-b'))[0];
        $this->assertNull($root['parent_span_id']);
        $this->assertSame($root['span_id'], $childA['parent_span_id']);
        $this->assertSame($root['span_id'], $childB['parent_span_id']);
    }

    private function countRows(): int
    {
        $row = $this->pdo->query('SELECT COUNT(*) AS c FROM flow_spans')->fetch(PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    private function trackerWith(BufferedPdoDriver $driver): FlowTracker
    {
        return new FlowTracker(
            new FlowConfig(enabled: true, component: 'core-test'),
            new PsrEventDispatcher(new Dispatcher()),
            $driver,
        );
    }
}
