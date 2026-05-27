<?php

namespace AdelinFeraru\NestedFlowTracker\Http\Controllers;

use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON read API over recorded flows. Served from the viewer route group, so it
 * shares the viewer's enable flag, gate, and middleware.
 */
class FlowApiController
{
    /**
     * List recent flows (root spans), newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FlowSpan::query()->whereNull('parent_span_id')->latest();

        if ($request->filled('component')) {
            $query->where('component', $request->input('component'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $flows = $query->paginate((int) $request->integer('per_page', 25))->withQueryString();

        return response()->json([
            'data' => array_map(fn (FlowSpan $span) => $this->summary($span), $flows->items()),
            'meta' => [
                'current_page' => $flows->currentPage(),
                'last_page' => $flows->lastPage(),
                'per_page' => $flows->perPage(),
                'total' => $flows->total(),
            ],
        ]);
    }

    /**
     * Return a single flow as a nested span tree.
     */
    public function show(string $trace): JsonResponse
    {
        $spans = FlowSpan::query()
            ->where('trace_id', $trace)
            ->orderBy('started_at')
            ->get();

        abort_if($spans->isEmpty(), 404);

        /** @var array<string, list<FlowSpan>> $childrenByParent */
        $childrenByParent = [];
        foreach ($spans as $span) {
            if ($span->parent_span_id !== null) {
                $childrenByParent[$span->parent_span_id][] = $span;
            }
        }

        $roots = $spans->whereNull('parent_span_id')->values();

        return response()->json([
            'trace_id' => $trace,
            'spans' => array_map(fn (FlowSpan $root) => $this->node($root, $childrenByParent), $roots->all()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(FlowSpan $span): array
    {
        return [
            'trace_id' => $span->trace_id,
            'name' => $span->name,
            'component' => $span->component,
            'status' => $span->status->value,
            'duration' => $span->duration,
            'started_at' => $span->started_at,
            'created_at' => $span->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param array<string, list<FlowSpan>> $childrenByParent
     * @return array<string, mixed>
     */
    private function node(FlowSpan $span, array $childrenByParent): array
    {
        return [
            'span_id' => $span->span_id,
            'parent_span_id' => $span->parent_span_id,
            'name' => $span->name,
            'component' => $span->component,
            'status' => $span->status->value,
            'duration' => $span->duration,
            'started_at' => $span->started_at,
            'context' => $span->context,
            'result' => $span->result,
            'children' => array_map(
                fn (FlowSpan $child) => $this->node($child, $childrenByParent),
                $childrenByParent[$span->span_id] ?? [],
            ),
        ];
    }
}
