@extends('flow::layout')

@section('title', 'Flows')

@section('content')
    <form method="GET" class="filters">
        <div>
            <label for="component">Component</label>
            <select name="component" id="component">
                <option value="">All</option>
                @foreach ($components as $component)
                    <option value="{{ $component }}" @selected($filters['component'] === $component)>{{ $component }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">All</option>
                @foreach (['ok', 'failed', 'running'] as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Filter</button>
    </form>

    @if ($flows->isEmpty())
        <div class="empty">No flows recorded yet.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Flow</th>
                    <th>Component</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($flows as $flow)
                    <tr>
                        <td><a href="{{ route('flow.show', $flow->trace_id) }}">{{ $flow->name }}</a></td>
                        <td class="muted">{{ $flow->component }}</td>
                        <td><span class="badge {{ $flow->status->value }}">{{ $flow->status->value }}</span></td>
                        <td class="mono">{{ number_format(($flow->duration ?? 0) * 1000, 1) }} ms</td>
                        <td class="muted">{{ $flow->created_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="pager">
            @if ($flows->previousPageUrl())
                <a href="{{ $flows->previousPageUrl() }}">&larr; Newer</a>
            @endif
            <span class="muted">Page {{ $flows->currentPage() }} of {{ $flows->lastPage() }}</span>
            @if ($flows->nextPageUrl())
                <a href="{{ $flows->nextPageUrl() }}">Older &rarr;</a>
            @endif
        </div>
    @endif
@endsection
