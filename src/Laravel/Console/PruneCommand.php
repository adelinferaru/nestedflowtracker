<?php

namespace AdelinFeraru\NestedFlowTracker\Laravel\Console;

use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'flow:prune {--days=30 : Delete flow spans older than this many days}';

    protected $description = 'Delete flow spans older than the given age.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        // Plain delete (bypasses the nested-set machinery) — we drop whole old flows.
        $deleted = FlowSpan::query()->where('created_at', '<', $cutoff)->getQuery()->delete();

        $this->info("Pruned {$deleted} flow span(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
