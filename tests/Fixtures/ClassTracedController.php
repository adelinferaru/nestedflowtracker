<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Fixtures;

use AdelinFeraru\NestedFlowTracker\Core\Attributes\Trace;

#[Trace]
class ClassTracedController
{
    public function index(): array
    {
        return ['ok' => true];
    }
}
