@php
    $status = $span->status->value;
    $ms = ($span->duration ?? 0) * 1000;
    $pct = $rootDuration > 0 ? min(100, round(($span->duration ?? 0) / $rootDuration * 100, 1)) : 0;
    $hasChildren = $span->children->isNotEmpty();
@endphp

@if ($hasChildren)
    <details class="node" open>
        <summary>
            <div class="row {{ $status }}">
                <span class="name" title="{{ $span->name }}">{{ $span->name }}</span>
                <span class="bar {{ $status === 'failed' ? 'failed' : '' }}"><span style="width: {{ $pct }}%"></span></span>
                <span class="dur mono">{{ number_format($ms, 1) }} ms</span>
            </div>
        </summary>
        <div class="children">
            @foreach ($span->children as $child)
                @include('flow::partials.span', ['span' => $child, 'rootDuration' => $rootDuration])
            @endforeach
        </div>
    </details>
@else
    <div class="node-leaf {{ $status }}">
        <span class="name" title="{{ $span->name }}">{{ $span->name }}</span>
        <span class="bar {{ $status === 'failed' ? 'failed' : '' }}"><span style="width: {{ $pct }}%"></span></span>
        <span class="dur mono">{{ number_format($ms, 1) }} ms</span>
    </div>
@endif
