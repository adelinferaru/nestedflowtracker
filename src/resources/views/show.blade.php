@extends('flow::layout')

@section('title', $root?->name ?? 'Flow')

@section('header')
    <span class="muted mono">{{ $trace }}</span>
@endsection

@section('content')
    <div style="display:flex; gap:24px; align-items:baseline; margin-bottom:8px;">
        <h1 style="margin:0; font-size:18px;">{{ $root?->name }}</h1>
        @if ($root)
            <span class="badge {{ $root->status->value }}">{{ $root->status->value }}</span>
            <span class="muted mono">{{ number_format(($root->duration ?? 0) * 1000, 1) }} ms total</span>
        @endif
    </div>

    <div class="tree">
        @forelse ($tree as $span)
            @include('flow::partials.span', ['span' => $span, 'rootDuration' => $rootDuration])
        @empty
            <div class="empty">This flow has no spans.</div>
        @endforelse
    </div>
@endsection
