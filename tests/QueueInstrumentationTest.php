<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Tests\Fixtures\LeakyJob;
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
        $this->assertNull($span->parent_span_id);
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

    public function test_sync_job_inside_an_open_flow_nests_under_it(): void
    {
        // The sync queue driver fires the same JobProcessing/JobProcessed events
        // as a real worker — the listener must not flush (and wipe) the caller's
        // open flow, it must nest the job under it.
        Flow::start('outer');
        SampleJob::dispatch();
        $outer = Flow::end();

        $this->assertNotNull($outer, 'the job listener must not wipe the outer flow');

        $jobRow = FlowSpan::query()->where('name', 'like', '%SampleJob%')->firstOrFail();
        $outerRow = FlowSpan::query()->where('name', 'outer')->firstOrFail();

        $this->assertSame($outerRow->trace_id, $jobRow->trace_id);
        $this->assertSame($outerRow->span_id, $jobRow->parent_span_id);
        $this->assertSame(SpanStatus::Ok, $outerRow->status);
    }

    public function test_spans_leaked_open_by_a_job_are_closed_with_the_job(): void
    {
        LeakyJob::dispatch();

        $jobRow = FlowSpan::query()->where('name', 'like', '%LeakyJob%')->firstOrFail();
        $leaked = FlowSpan::query()->where('name', 'leaked')->firstOrFail();

        // The listener closes the leaked child first, then the job's own span —
        // not just whatever happens to be innermost.
        $this->assertSame(SpanStatus::Ok, $jobRow->status);
        $this->assertNotNull($jobRow->duration);
        $this->assertSame($jobRow->span_id, $leaked->parent_span_id);
        $this->assertNotNull($leaked->duration);
    }
}
