<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Tests\Fixtures\SampleJob;
use RuntimeException;

class QueueInstrumentationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('flow.auto.queue', true);
    }

    public function test_processed_job_records_a_root_span(): void
    {
        SampleJob::dispatch();

        $span = FlowSpan::query()->firstOrFail();
        $this->assertStringContainsString('SampleJob', $span->name);
        $this->assertNull($span->parent_id);
        $this->assertSame(SpanStatus::Ok, $span->status);
        $this->assertSame('sync', $span->context['connection']);
    }

    public function test_failing_job_records_a_failed_span(): void
    {
        try {
            SampleJob::dispatch(shouldFail: true);
            $this->fail('The failing job did not throw.');
        } catch (RuntimeException $e) {
            $this->assertSame('job failed', $e->getMessage());
        }

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame(SpanStatus::Failed, $span->status);
        $this->assertSame(RuntimeException::class, $span->result['exception'] ?? null);
    }

    public function test_each_job_is_an_isolated_trace(): void
    {
        SampleJob::dispatch();
        SampleJob::dispatch();

        $this->assertSame(2, FlowSpan::query()->count());
        $this->assertSame(2, FlowSpan::query()->distinct()->pluck('trace_id')->count());
    }
}
