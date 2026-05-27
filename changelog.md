# Changelog

All notable changes to `nestedflowtracker` are documented here. This project follows
[Semantic Versioning](https://semver.org) and [Keep a Changelog](https://keepachangelog.com).

## [2.0.0] - 2026-05-27

A ground-up rewrite into a modern, injectable flow tracer with an ergonomic span API,
auto-instrumentation, a built-in viewer, and W3C Trace Context propagation.

### Added
- `Flow::span($name, $closure)` — the recommended API: opens/closes around the callback,
  exception-safe, returns the callback's value, and marks failed spans.
- Manual `Flow::start()` / `Flow::end()` for non-closure cases (LIFO, instance-based).
- `Flow` facade, `flow()` helper, and constructor injection (`FlowTracker`).
- Opt-in auto-instrumentation: HTTP middleware (root span per request) and queue listeners
  (root span per job) via `flow.auto.http` / `flow.auto.queue`.
- Built-in viewer UI at `/flow` (index + collapsible flow tree), no build step; gated by a
  `viewFlow` gate outside local. Enable with `flow.viewer.enabled`.
- W3C Trace Context propagation: `Http::withFlowTrace()` (outbound) and automatic inbound
  continuation; `TraceContext` value object.
- Artisan commands `flow:show {trace}` and `flow:prune --days`.
- `SpanStarted` / `SpanFinished` events and a `SpanStatus` enum.
- Container-scoped service — safe across Octane requests and queued jobs.

### Changed
- **Breaking:** the static `NestedFlowTracker` API is replaced by the injectable `FlowTracker`
  / `Flow` facade / `flow()` helper.
- **Breaking:** model `FNTrack` → `FlowSpan`; table `fn_flow_tracks` → `flow_spans` (adds
  `name`, `status`).
- **Breaking:** `tracker_id` (session-based) → `trace_id` (32-hex, W3C/OTel-style, held on the
  instance — works in CLI/queues).
- **Breaking:** config `nestedflowtracker.php` (`FLOW_TRACKER_*`) → `flow.php` (`FLOW_*`).
- **Breaking:** requires PHP 8.1+ and Laravel 10/11/12; dropped PHP 7.x and Laravel 5–9.

### Removed
- The dead singleton scaffolding and session coupling of the 1.x API.

## [1.0] - 2019

- Initial release with the static `NestedFlowTracker::startTrack()` / `endTrack()` API.

[2.0.0]: https://github.com/adelinferaru/nestedflowtracker/releases/tag/2.0.0
