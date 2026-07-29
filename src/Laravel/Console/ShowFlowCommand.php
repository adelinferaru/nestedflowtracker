<?php

namespace AdelinFeraru\NestedFlowTracker\Laravel\Console;

use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Laravel\Support\SpanMeta;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

class ShowFlowCommand extends Command
{
    protected $signature = 'flow:show {trace : The trace id to display}';

    protected $description = 'Print a recorded flow as a tree.';

    public function handle(): int
    {
        $trace = (string) $this->argument('trace');

        $spans = FlowSpan::query()->where('trace_id', $trace)->orderBy('started_at')->get();

        if ($spans->isEmpty()) {
            $this->error("No flow found for trace {$trace}.");

            return self::FAILURE;
        }

        /** @var array<string, list<FlowSpan>> $childrenByParent */
        $childrenByParent = [];
        foreach ($spans as $span) {
            if ($span->parent_span_id !== null) {
                $childrenByParent[$span->parent_span_id][] = $span;
            }
        }

        foreach ($spans->whereNull('parent_span_id') as $root) {
            $this->printSpan($root, $childrenByParent, 0);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, list<FlowSpan>> $childrenByParent
     */
    private function printSpan(FlowSpan $span, array $childrenByParent, int $depth): void
    {
        $color = match ($span->status) {
            SpanStatus::Failed => 'red',
            SpanStatus::Ok => 'green',
            default => 'gray',
        };

        $line = sprintf(
            '%s%s  <fg=gray>%s ms</> <fg=%s>%s</>',
            str_repeat('  ', $depth),
            $span->name,
            number_format(($span->duration ?? 0) * 1000, 1),
            $color,
            $span->status->value,
        );

        // Same metadata shape as the viewer: message, then context/result pairs.
        $meta = $span->message !== null && $span->message !== '' ? [$span->message] : [];
        $meta = [...$meta, ...SpanMeta::pairs($span->context), ...SpanMeta::pairs($span->result)];

        if ($meta !== []) {
            $line .= '  <fg=gray>' . OutputFormatter::escape(implode(' ', $meta)) . '</>';
        }

        $this->line($line);

        foreach ($childrenByParent[$span->span_id] ?? [] as $child) {
            $this->printSpan($child, $childrenByParent, $depth + 1);
        }
    }
}
