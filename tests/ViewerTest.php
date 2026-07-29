<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;
use Illuminate\Support\Facades\Gate;

class ViewerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.viewer.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewFlow', fn ($user = null) => true);
    }

    public function test_index_lists_recorded_flows(): void
    {
        Flow::span('checkout', fn () => null);

        $this->get('/flow')
            ->assertOk()
            ->assertSee('checkout');
    }

    public function test_index_can_filter_by_status(): void
    {
        Flow::span('good', fn () => null);

        $this->get('/flow?status=failed')
            ->assertOk()
            ->assertDontSee('good');
    }

    public function test_show_renders_the_span_tree(): void
    {
        Flow::span('checkout', function () {
            Flow::span('charge card', fn () => null);
        });
        $trace = Flow::traceId();

        $this->get('/flow/' . $trace)
            ->assertOk()
            ->assertSee('checkout')
            ->assertSee('charge card');
    }

    public function test_show_renders_span_context(): void
    {
        Flow::span('render', fn () => null, [
            'context' => ['boot_ms' => 129.3, 'q_type' => 'MULTIPLE_CHOICE'],
        ]);
        $trace = Flow::traceId();

        $this->get('/flow/' . $trace)
            ->assertOk()
            ->assertSee('boot_ms=129.3')
            ->assertSee('q_type=MULTIPLE_CHOICE');
    }

    public function test_show_renders_failure_details_from_result(): void
    {
        try {
            Flow::span('boom', fn () => throw new \RuntimeException('kaboom'));
        } catch (\RuntimeException) {
        }
        $trace = Flow::traceId();

        $this->get('/flow/' . $trace)
            ->assertOk()
            ->assertSee('message=kaboom');
    }

    public function test_show_omits_the_meta_row_for_spans_without_metadata(): void
    {
        Flow::span('bare', fn () => null);
        $trace = Flow::traceId();

        $this->get('/flow/' . $trace)
            ->assertOk()
            ->assertSee('bare')
            ->assertDontSee('class="span-meta"', false);
    }

    public function test_unknown_trace_returns_404(): void
    {
        $this->get('/flow/does-not-exist')->assertNotFound();
    }

    public function test_show_handles_legacy_rows_without_span_ids(): void
    {
        // Pre-2.1 rows have a NULL span_id. Looking children up by a null key
        // used to land on the roots group (null coerces to the '' array key),
        // hanging the root under itself — infinite recursion in the partial.
        FlowSpan::query()->insert([
            'trace_id' => str_repeat('ab', 16),
            'span_id' => null,
            'parent_span_id' => null,
            'name' => 'legacy root',
            'component' => 'test-component',
            'status' => 'ok',
            'duration' => 0.1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/flow/' . str_repeat('ab', 16))
            ->assertOk()
            ->assertSee('legacy root');
    }
}
