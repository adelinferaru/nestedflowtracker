<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Drivers;

use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Tests\TestCase;

class NullDriverTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.driver', 'null');
    }

    public function test_api_works_but_nothing_is_stored(): void
    {
        $value = Flow::span('task', fn () => 'result');

        $this->assertSame('result', $value);
        $this->assertSame(0, FlowSpan::query()->count());
    }
}
