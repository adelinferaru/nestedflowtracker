<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Drivers;

use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Tests\TestCase;
use Illuminate\Support\Facades\Log;

class LogDriverTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.driver', 'log');
    }

    public function test_span_is_logged_and_not_stored(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'flow.span'
                && $context['name'] === 'task'
                && $context['status'] === 'ok');

        Flow::span('task', fn () => null);

        $this->assertSame(0, FlowSpan::query()->count());
    }
}
