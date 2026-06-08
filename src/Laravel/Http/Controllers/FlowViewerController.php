<?php

namespace AdelinFeraru\NestedFlowTracker\Laravel\Http\Controllers;

use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FlowViewerController
{
    /**
     * List recent flows (the root span of each trace).
     */
    public function index(Request $request): Response
    {
        // Roots are spans with no parent span (parent_span_id is null for both the
        // immediate and buffered database drivers).
        $query = FlowSpan::query()->whereNull('parent_span_id')->latest();

        if ($request->filled('component')) {
            $query->where('component', $request->input('component'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $flows = $query->paginate(25)->withQueryString();

        $components = FlowSpan::query()
            ->whereNull('parent_span_id')
            ->distinct()
            ->orderBy('component')
            ->pluck('component');

        return response()->view('flow::index', [
            'flows' => $flows,
            'components' => $components,
            'filters' => [
                'component' => $request->input('component'),
                'status' => $request->input('status'),
            ],
        ]);
    }

    /**
     * Show a single flow as a timed tree.
     */
    public function show(string $trace): Response
    {
        $spans = FlowSpan::query()
            ->where('trace_id', $trace)
            ->orderBy('started_at')
            ->get();

        abort_if($spans->isEmpty(), 404);

        // Build the tree from span ids and hang each node's children off it.
        $childrenByParent = $spans->groupBy('parent_span_id');
        foreach ($spans as $span) {
            $span->setRelation('children', $childrenByParent->get($span->span_id) ?? collect());
        }

        $root = $spans->first();

        return response()->view('flow::show', [
            'trace' => $trace,
            'root' => $root,
            'tree' => $spans->whereNull('parent_span_id')->values(),
            'rootDuration' => (float) ($root->duration ?? 0.0),
        ]);
    }
}
