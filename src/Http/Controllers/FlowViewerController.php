<?php

namespace AdelinFeraru\NestedFlowTracker\Http\Controllers;

use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FlowViewerController
{
    /**
     * List recent flows (the root span of each trace).
     */
    public function index(Request $request): Response
    {
        $query = FlowSpan::query()->whereNull('parent_id')->latest();

        if ($request->filled('component')) {
            $query->where('component', $request->input('component'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $flows = $query->paginate(25)->withQueryString();

        $components = FlowSpan::query()
            ->whereNull('parent_id')
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
            ->orderBy('_lft')
            ->get();

        abort_if($spans->isEmpty(), 404);

        // Build the tree from parent ids and hang each node's children off it.
        $childrenByParent = $spans->groupBy('parent_id');
        foreach ($spans as $span) {
            $span->setRelation('children', $childrenByParent->get($span->id) ?? collect());
        }

        $root = $spans->first();

        return response()->view('flow::show', [
            'trace' => $trace,
            'root' => $root,
            'tree' => $spans->whereNull('parent_id')->values(),
            'rootDuration' => (float) ($root->duration ?? 0.0),
        ]);
    }
}
