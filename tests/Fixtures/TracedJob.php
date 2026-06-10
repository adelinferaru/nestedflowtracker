<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Fixtures;

use AdelinFeraru\NestedFlowTracker\Core\Attributes\Trace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

#[Trace('traced job')]
class TracedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public bool $shouldFail = false,
    ) {
    }

    public function handle(): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('traced job failed');
        }
    }
}
