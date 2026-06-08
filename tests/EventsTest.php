<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Core\Events\SpanFinished;
use AdelinFeraru\NestedFlowTracker\Core\Events\SpanStarted;
use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;
use Illuminate\Support\Facades\Event;

class EventsTest extends TestCase
{
    public function test_span_dispatches_started_and_finished_events(): void
    {
        Event::fake([SpanStarted::class, SpanFinished::class]);

        Flow::span('work', fn () => null);

        Event::assertDispatched(SpanStarted::class, fn (SpanStarted $e) => $e->span->name === 'work');
        Event::assertDispatched(SpanFinished::class, fn (SpanFinished $e) => $e->span->name === 'work');
    }

    public function test_no_events_when_disabled(): void
    {
        config(['flow.enabled' => false]);
        Event::fake([SpanStarted::class, SpanFinished::class]);

        Flow::span('work', fn () => null);

        Event::assertNotDispatched(SpanStarted::class);
        Event::assertNotDispatched(SpanFinished::class);
    }
}
