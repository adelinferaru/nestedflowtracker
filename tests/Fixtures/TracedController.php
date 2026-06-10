<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Fixtures;

use AdelinFeraru\NestedFlowTracker\Core\Attributes\Trace;

class TracedController
{
    #[Trace]
    public function show(): array
    {
        return ['ok' => true];
    }

    #[Trace('named span')]
    public function named(): array
    {
        return ['ok' => true];
    }

    public function untraced(): array
    {
        return ['ok' => true];
    }

    #[Trace]
    public function failing(): never
    {
        abort(500, 'boom');
    }
}
