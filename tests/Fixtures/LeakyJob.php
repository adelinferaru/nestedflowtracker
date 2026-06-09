<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Fixtures;

use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Opens a span and never closes it — exercises the queue listeners' cleanup of
 * spans a job leaks open.
 */
class LeakyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        Flow::start('leaked');
    }
}
