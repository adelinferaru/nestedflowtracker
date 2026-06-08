<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;

class CommandsTest extends TestCase
{
    public function test_prune_removes_old_flows_and_keeps_recent_ones(): void
    {
        Flow::span('old', fn () => null);
        $oldTrace = Flow::traceId();
        FlowSpan::where('trace_id', $oldTrace)->update(['created_at' => now()->subDays(40)]);

        Flow::flush();
        Flow::span('recent', fn () => null);

        $this->artisan('flow:prune', ['--days' => 30])->assertExitCode(0);

        $this->assertSame(0, FlowSpan::query()->where('name', 'old')->count());
        $this->assertSame(1, FlowSpan::query()->where('name', 'recent')->count());
    }

    public function test_show_prints_the_flow_tree(): void
    {
        Flow::span('checkout', function () {
            Flow::span('charge card', fn () => null);
        });
        $trace = Flow::traceId();

        $this->artisan('flow:show', ['trace' => $trace])
            ->expectsOutputToContain('checkout')
            ->expectsOutputToContain('charge card')
            ->assertExitCode(0);
    }

    public function test_show_fails_for_an_unknown_trace(): void
    {
        $this->artisan('flow:show', ['trace' => 'does-not-exist'])->assertExitCode(1);
    }
}
