<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

class ModelCastTest extends TestCase
{
    public function test_context_and_result_round_trip_as_arrays(): void
    {
        Flow::start('task', ['context' => ['route' => '/checkout']]);
        Flow::end(['result' => ['status' => 'paid', 'amount' => 1299]]);

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame(['route' => '/checkout'], $span->context);
        $this->assertSame(['status' => 'paid', 'amount' => 1299], $span->result);
    }

    public function test_status_is_cast_to_the_enum(): void
    {
        Flow::span('task', fn () => null);

        $this->assertInstanceOf(SpanStatus::class, FlowSpan::query()->firstOrFail()->status);
    }

    public function test_user_is_carried_onto_spans(): void
    {
        Flow::setUser(99);
        Flow::span('task', fn () => null);

        $this->assertSame('99', FlowSpan::query()->firstOrFail()->user_id);
    }
}
