<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Drivers;

use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class BufferedDatabaseDriverTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.buffer', true);
    }

    public function test_nothing_is_written_until_the_root_closes(): void
    {
        Flow::start('root');
        Flow::start('child');
        Flow::end();   // child closed, but root still open

        $this->assertSame(0, FlowSpan::query()->count());

        Flow::end();   // root closed -> flush

        $this->assertSame(2, FlowSpan::query()->count());
    }

    public function test_whole_flow_is_written_in_a_single_insert(): void
    {
        DB::connection('testing')->enableQueryLog();

        Flow::span('checkout', function () {
            Flow::span('a', fn () => null);
            Flow::span('b', fn () => null);
        });

        $inserts = collect(DB::connection('testing')->getQueryLog())
            ->filter(fn ($q) => str_starts_with(strtolower(trim($q['query'])), 'insert'))
            ->count();

        $this->assertSame(1, $inserts);
        $this->assertSame(3, FlowSpan::query()->count());
    }

    public function test_tree_is_reconstructable_via_parent_span_id(): void
    {
        Flow::span('checkout', function () {
            Flow::span('charge card', fn () => null);
        });

        $root = FlowSpan::query()->whereNull('parent_span_id')->firstOrFail();
        $child = FlowSpan::query()->where('name', 'charge card')->firstOrFail();

        $this->assertSame('checkout', $root->name);
        $this->assertSame($root->span_id, $child->parent_span_id);
    }

    public function test_large_flow_chunks_into_batches_under_sqlite_param_limit(): void
    {
        // 200 spans * 14 columns = 2800 placeholders — well past SQLite's
        // default 999-variable cap. The driver must chunk so this still inserts.
        Flow::span('root', function () {
            for ($i = 0; $i < 200; $i++) {
                Flow::span("child-{$i}", fn () => null);
            }
        });

        $this->assertSame(201, FlowSpan::query()->count());
    }
}
