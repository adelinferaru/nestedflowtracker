@php
    /* Metadata line under a span row: message, then context/result as key=value
       pairs. Rendered only when the span actually carries metadata, so bare
       spans keep their single-row layout. */
    $metaPairs = array_merge(
        \AdelinFeraru\NestedFlowTracker\Laravel\Support\SpanMeta::pairs($span->context),
        \AdelinFeraru\NestedFlowTracker\Laravel\Support\SpanMeta::pairs($span->result),
    );
    $metaMessage = $span->message !== null && $span->message !== '' ? $span->message : null;
@endphp

@if ($metaMessage !== null || $metaPairs !== [])
    <div class="span-meta">
        @if ($metaMessage !== null)
            <span class="meta-message">{{ $metaMessage }}</span>
        @endif
        @foreach ($metaPairs as $pair)
            <span class="kv mono">{{ $pair }}</span>
        @endforeach
    </div>
@endif
