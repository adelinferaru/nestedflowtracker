<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

class BenchmarkCommandTest extends TestCase
{
    public function test_benchmark_runs_and_leaves_no_data(): void
    {
        $this->artisan('flow:benchmark', ['--flows' => 3, '--spans' => 2])
            ->assertExitCode(0);

        // The database scenario runs inside a rolled-back transaction.
        $this->assertSame(0, FlowSpan::query()->count());
    }
}
