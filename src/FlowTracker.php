<?php

namespace AdelinFeraru\NestedFlowTracker;

use AdelinFeraru\NestedFlowTracker\Core\FlowConfig;
use AdelinFeraru\NestedFlowTracker\Core\Span;
use AdelinFeraru\NestedFlowTracker\Drivers\SpanDriver;
use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Events\SpanFinished;
use AdelinFeraru\NestedFlowTracker\Events\SpanStarted;
use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Records nested, timed spans for a flow. Instance state is held per-resolution
 * (bound as a scoped singleton), so each HTTP request / queued job gets its own
 * open-spans stack and trace id with no leakage across requests under Octane.
 */
class FlowTracker
{
    /** @var list<array{span: Span, start: float}> Open spans, innermost last. */
    private array $stack = [];

    private ?string $traceId = null;

    private int|string|null $userId = null;

    public function __construct(
        private readonly FlowConfig $config,
        private readonly EventDispatcherInterface $events,
        private readonly SpanDriver $driver,
    ) {
    }

    /**
     * Whether tracking is active. When false, span() is a transparent pass-through.
     */
    public function enabled(): bool
    {
        return $this->config->enabled;
    }

    /**
     * Trace a callback as a span: opens before, closes after (even on exception),
     * and returns the callback's value untouched. This is the recommended API.
     *
     * @template TReturn
     * @param Closure(Span|null): TReturn $callback
     * @param array<string, mixed> $options
     * @return TReturn
     */
    public function span(string $name, Closure $callback, array $options = []): mixed
    {
        if (! $this->enabled()) {
            return $callback(null);
        }

        $span = $this->start($name, $options);

        try {
            return $callback($span);
        } catch (Throwable $e) {
            $this->fail($e);

            throw $e;
        } finally {
            $this->end();
        }
    }

    /**
     * Open a span manually. Prefer span() unless you cannot wrap the work in a closure.
     *
     * @param array<string, mixed> $options trace_id, root, parent_id, user_id, component,
     *                                       message, context, result.
     */
    public function start(string $name, array $options = []): ?Span
    {
        if (! $this->enabled()) {
            return null;
        }

        $start = microtime(true);

        $span = $this->newSpan();
        $span->name = $name;
        $span->span_id = bin2hex(random_bytes(8));
        $span->started_at = number_format($start, 6, '.', '');
        $span->status = SpanStatus::Running;
        $span->component = $options['component'] ?? $this->config->component;
        $span->message = $options['message'] ?? null;

        if (array_key_exists('context', $options)) {
            $span->context = $options['context'];
        }

        if (array_key_exists('result', $options)) {
            $span->result = $options['result'];
        }

        if (isset($options['user_id'])) {
            $this->userId = $options['user_id'];
        }
        if ($this->userId !== null) {
            $span->user_id = $this->userId;
        }

        if (! empty($options['trace_id'])) {
            $this->setTraceId((string) $options['trace_id']);
        }

        // Nest under the currently open span unless flagged as a root.
        $parent = empty($options['root']) ? $this->currentSpan() : null;
        if ($parent !== null) {
            $span->trace_id = $parent->trace_id;
            $span->parent_span_id = $parent->span_id;
        } else {
            $span->trace_id = $this->traceId ??= $this->newTraceId();
        }

        // An explicit parent_id attaches the span to that node (database driver).
        if (! empty($options['parent_id'])) {
            $span->parent_id = $options['parent_id'];
        }

        // The driver decides how/whether to persist the span and place it in the tree.
        $this->driver->opening($span, $parent);

        $this->stack[] = ['span' => $span, 'start' => $start];

        $this->events->dispatch(new SpanStarted($span));

        return $span;
    }

    /**
     * Close the most recently opened span, recording its duration.
     *
     * @param array<string, mixed> $options message, context, result, status overrides.
     */
    public function end(array $options = []): ?Span
    {
        if (! $this->enabled() || $this->stack === []) {
            return null;
        }

        $entry = array_pop($this->stack);
        $span = $entry['span'];
        $span->duration = microtime(true) - $entry['start'];

        if ($span->status === SpanStatus::Running) {
            $span->status = SpanStatus::Ok;
        }

        foreach (['message', 'context', 'result', 'status'] as $key) {
            if (array_key_exists($key, $options)) {
                $span->{$key} = $options[$key];
            }
        }

        $this->driver->closing($span);

        $this->events->dispatch(new SpanFinished($span));

        return $span;
    }

    /**
     * Mark the currently open span as failed and record the exception. Saved when the
     * span is closed.
     *
     * @param array<string, mixed> $context Extra data to merge into the result.
     */
    public function fail(Throwable $e, array $context = []): void
    {
        $span = $this->currentSpan();
        if ($span === null) {
            return;
        }

        $span->status = SpanStatus::Failed;
        $span->result = array_merge([
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ], $context);
    }

    /**
     * The innermost open span, or null when no span is open.
     */
    public function currentSpan(): ?Span
    {
        $entry = end($this->stack);

        return $entry === false ? null : $entry['span'];
    }

    /**
     * The id grouping every span of the current flow (null until the first span opens).
     */
    public function traceId(): ?string
    {
        return $this->traceId;
    }

    /**
     * Continue an existing flow — e.g. from an inbound request's trace id.
     */
    public function setTraceId(string $traceId): static
    {
        $this->traceId = $traceId;

        return $this;
    }

    /**
     * Associate the current flow with a user.
     */
    public function setUser(int|string|null $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Reset all per-flow state. Called between requests/jobs by the scoped lifecycle.
     */
    public function flush(): void
    {
        $this->stack = [];
        $this->traceId = null;
        $this->userId = null;
    }

    private function newSpan(): Span
    {
        return new Span();
    }

    private function newTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
