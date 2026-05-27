<?php

namespace AdelinFeraru\NestedFlowTracker\Console;

use AdelinFeraru\NestedFlowTracker\FlowTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Measures tracking overhead across the storage drivers, so you can see what
 * tracking costs before turning it on (or before optimizing it).
 */
class BenchmarkCommand extends Command
{
    protected $signature = 'flow:benchmark {--flows=200 : Number of flows} {--spans=5 : Child spans per flow}';

    protected $description = 'Benchmark flow-tracking overhead across drivers.';

    public function handle(): int
    {
        $flows = max(1, (int) $this->option('flows'));
        $spans = max(0, (int) $this->option('spans'));

        $this->info("Benchmarking {$flows} flows x " . ($spans + 1) . ' spans each...');

        $rows = [
            $this->bench('disabled', ['flow.enabled' => false], $flows, $spans),
            $this->bench('null driver', ['flow.enabled' => true, 'flow.driver' => 'null'], $flows, $spans),
            $this->bench('database driver', ['flow.enabled' => true, 'flow.driver' => 'database'], $flows, $spans, true),
        ];

        $this->table(['Scenario', 'Total', 'µs / span'], $rows);

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{0: string, 1: string, 2: string}
     */
    private function bench(string $label, array $config, int $flows, int $spans, bool $transactional = false): array
    {
        foreach ($config as $key => $value) {
            config([$key => $value]);
        }

        // Re-resolve the tracker so it picks up the scenario's driver/config.
        $this->laravel->forgetScopedInstances();
        $tracker = $this->laravel->make(FlowTracker::class);

        $connection = config('flow.connection') ?? config('database.default');

        if ($transactional) {
            DB::connection($connection)->beginTransaction();
        }

        $start = microtime(true);

        for ($i = 0; $i < $flows; $i++) {
            $tracker->flush();
            $tracker->span('root', function () use ($tracker, $spans) {
                for ($j = 0; $j < $spans; $j++) {
                    $tracker->span('child', fn () => null);
                }
            });
        }

        $elapsed = microtime(true) - $start;

        if ($transactional) {
            // Roll back so the benchmark leaves no data behind.
            DB::connection($connection)->rollBack();
        }

        $totalSpans = $flows * ($spans + 1);
        $perSpan = $totalSpans > 0 ? $elapsed * 1_000_000 / $totalSpans : 0.0;

        return [$label, number_format($elapsed * 1000, 1) . ' ms', number_format($perSpan, 2)];
    }
}
