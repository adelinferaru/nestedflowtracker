<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Tests\Fixtures\SampleJob;
use AdelinFeraru\NestedFlowTracker\Tests\Fixtures\TracedJob;
use RuntimeException;

/**
 * flow.auto.queue stays OFF here — only jobs carrying #[Trace] are recorded.
 */
class AttributeQueueTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('queue.default', 'sync');
    }

    public function test_attributed_job_records_a_span_without_auto_queue(): void
    {
        TracedJob::dispatch();

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame('traced job', $span->name);
        $this->assertSame(SpanStatus::Ok, $span->status);
        $this->assertNull($span->parent_span_id);
    }

    public function test_job_without_attribute_records_nothing(): void
    {
        SampleJob::dispatch();

        $this->assertSame(0, FlowSpan::query()->count());
    }

    public function test_failing_attributed_job_records_a_failed_span(): void
    {
        try {
            TracedJob::dispatch(shouldFail: true);
            $this->fail('The failing job did not throw.');
        } catch (RuntimeException $e) {
            $this->assertSame('traced job failed', $e->getMessage());
        }

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame('traced job', $span->name);
        $this->assertSame(SpanStatus::Failed, $span->status);
        $this->assertSame(RuntimeException::class, $span->result['exception'] ?? null);
    }
}
