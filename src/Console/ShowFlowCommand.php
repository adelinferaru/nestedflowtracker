<?php

namespace AdelinFeraru\NestedFlowTracker\Console;

use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use Illuminate\Console\Command;

class ShowFlowCommand extends Command
{
    protected $signature = 'flow:show {trace : The trace id to display}';

    protected $description = 'Print a recorded flow as a tree.';

    public function handle(): int
    {
        $trace = (string) $this->argument('trace');

        $spans = FlowSpan::query()->where('trace_id', $trace)->orderBy('_lft')->get();

        if ($spans->isEmpty()) {
            $this->error("No flow found for trace {$trace}.");

            return self::FAILURE;
        }

        /** @var array<int, list<FlowSpan>> $childrenByParent */
        $childrenByParent = [];
        foreach ($spans as $span) {
            if ($span->parent_id !== null) {
                $childrenByParent[$span->parent_id][] = $span;
            }
        }

        foreach ($spans->whereNull('parent_id') as $root) {
            $this->printSpan($root, $childrenByParent, 0);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, list<FlowSpan>> $childrenByParent
     */
    private function printSpan(FlowSpan $span, array $childrenByParent, int $depth): void
    {
        $color = match ($span->status) {
            SpanStatus::Failed => 'red',
            SpanStatus::Ok => 'green',
            default => 'gray',
        };

        $this->line(sprintf(
            '%s%s  <fg=gray>%s ms</> <fg=%s>%s</>',
            str_repeat('  ', $depth),
            $span->name,
            number_format(($span->duration ?? 0) * 1000, 1),
            $color,
            $span->status->value,
        ));

        foreach ($childrenByParent[$span->id] ?? [] as $child) {
            $this->printSpan($child, $childrenByParent, $depth + 1);
        }
    }
}
