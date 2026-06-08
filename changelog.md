# Changelog

All notable changes to `nestedflowtracker` are documented here. This project follows
[Semantic Versioning](https://semver.org) and [Keep a Changelog](https://keepachangelog.com).

## [3.0.0] - 2026-06-08

### Changed (breaking — namespace reorganization)
The package is split internally into a framework-agnostic **Core** and a **Laravel** adapter so
users without Laravel can drive `FlowTracker` directly. Public API is unchanged in behaviour;
namespaces and a few class names moved.

| 2.x | 3.0 |
| --- | --- |
| `AdelinFeraru\NestedFlowTracker\FlowTracker` | `…\Core\FlowTracker` |
| `…\TraceContext` | `…\Core\TraceContext` |
| `…\Enums\SpanStatus` | `…\Core\Enums\SpanStatus` |
| `…\Events\SpanStarted` / `SpanFinished` | `…\Core\Events\SpanStarted` / `SpanFinished` |
| `…\Drivers\SpanDriver` / `NullDriver` / `LogDriver` / `OtelDriver` | `…\Core\Drivers\*` |
| `…\Otel\OtelExporter` | `…\Core\Otel\OtelExporter` (now PSR-18/PSR-17) |
| `…\FlowServiceProvider` | `…\Laravel\FlowServiceProvider` |
| `…\Facades\Flow` | `…\Laravel\Facades\Flow` |
| `…\Models\FlowSpan` | `…\Laravel\Eloquent\FlowSpan` |
| `…\Drivers\DatabaseDriver` | `…\Laravel\Drivers\EloquentDatabaseDriver` |
| `…\Drivers\BufferedDatabaseDriver` | `…\Laravel\Drivers\EloquentBufferedDriver` |
| `…\Http\*` | `…\Laravel\Http\*` |
| `…\Console\*` | `…\Laravel\Console\*` |
| `…\Otel\ExportTrace` | `…\Laravel\Otel\ExportTrace` |

`Flow::span()`, `flow()`, the artisan commands, the viewer routes, the `flow.*` config keys and
`FLOW_*` env vars, and the `flow_spans` schema are all unchanged — most users only need to update
`use` statements and pull `composer update` (composer auto-discovery picks up the new provider).

### Added
- **`Core\Span`** — plain DTO that `FlowTracker`, the drivers, the events and the OTLP exporter
  operate on. Replaces the Eloquent `FlowSpan` for in-memory use; persistence is now an Eloquent
  detail of the Laravel adapter.
- **`Core\FlowConfig`** — readonly DTO that `FlowTracker` takes instead of Laravel's config
  repository. Non-Laravel callers construct one directly.
- **PSR-14 event boundary** — `FlowTracker` now depends on `Psr\EventDispatcher\EventDispatcherInterface`.
  The Laravel adapter wraps the framework's dispatcher in `Laravel\Bridge\PsrEventDispatcher`.
- **`Core\Drivers\PdoDriver` + `BufferedPdoDriver`** — framework-agnostic DB drivers backed by
  plain PDO (sqlite/mysql/pgsql), with `Core\Drivers\PdoSchema::create()` to spin up the lean
  `flow_spans` table.
- **PSR-3 `Core\Drivers\LogDriver`** — takes any `LoggerInterface`. The Laravel provider passes
  `Log::channel(...)` automatically when `flow.driver=log`.
- **PSR-18 / PSR-17 `Core\Otel\OtelExporter`** — accepts a `ClientInterface` + the two factory
  interfaces. The Laravel adapter binds Guzzle by default; downstream apps can override.
- Top-level PSR contracts (`psr/log`, `psr/event-dispatcher`, `psr/http-client`, `psr/http-factory`,
  `psr/http-message`) and `guzzlehttp/guzzle` are now production deps.

## [2.5.1] - 2026-05-27

### Fixed
- `license.md` declared the EU Public License v1.1 while `composer.json` and the README declared
  MIT. Standardized the license file on **MIT** so all three agree (no code changes).

## [2.5.0] - 2026-05-27

### Added
- JSON read API for the viewer: `GET {path}/api/flows` (paginated list with component/status
  filters) and `GET {path}/api/flows/{trace}` (a flow as a nested span tree). Served from the
  viewer route group, so it shares the viewer's enable flag, `viewFlow` gate, and middleware.

## [2.4.0] - 2026-05-27

### Added
- **Buffered writes** for the database driver (`flow.buffer` / `FLOW_BUFFER`): a flow is held in
  memory and written in a single bulk insert when its root span closes — about 8× faster than the
  default per-span writes in the benchmark. Off by default; spans are persisted only once the flow
  completes.

### Changed
- The viewer, `flow:show`, and the OTel export now reconstruct the tree from `parent_span_id`
  (ordered by `started_at`) instead of the nested set, so they work for both the immediate and
  buffered drivers. No migration required.

## [2.3.0] - 2026-05-27

### Added
- `flow:benchmark` command to measure tracking overhead per driver (disabled / null / database).
  The database run is wrapped in a transaction and rolled back, so it leaves no data behind.
- Index on `flow_spans.created_at` (speeds the viewer's recent-flows listing and `flow:prune`).

### Upgrade notes
- Run the new migration: `php artisan vendor:publish --tag="flow-migrations" && php artisan migrate`.

## [2.2.0] - 2026-05-27

### Added
- **Pluggable storage drivers** via `flow.driver`: `database` (default; the nested-set store that
  powers the viewer and commands), `log` (structured log lines), `null` (discard), and `otel`
  (send spans straight to an OTLP collector with no database). `FlowTracker` now delegates
  persistence to a `SpanDriver`.
- `parent_span_id` column on `flow_spans` so parent linkage works without database row ids
  (enables the non-database drivers and simplifies OTLP export).

### Upgrade notes
- Run the new migration: `php artisan vendor:publish --tag="flow-migrations" && php artisan migrate`.

## [2.1.0] - 2026-05-27

### Added
- **OpenTelemetry export** (opt-in, no SDK dependency): when a flow's root span closes, the whole
  trace is exported as OTLP-JSON to an OTLP/HTTP collector (`{endpoint}/v1/traces`) on a queue.
  Configure via `flow.otel.*` (`FLOW_OTEL_ENABLED` / `FLOW_OTEL_ENDPOINT`).
- `span_id` (16-hex) and microsecond `started_at` columns on `flow_spans` for correct OTLP span
  ids and timing. The outbound `traceparent` now uses the real `span_id`.

### Upgrade notes
- Run the new migration: `php artisan vendor:publish --tag="flow-migrations" && php artisan migrate`.

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

[2.5.1]: https://github.com/adelinferaru/nestedflowtracker/releases/tag/2.5.1
[2.5.0]: https://github.com/adelinferaru/nestedflowtracker/releases/tag/2.5.0
[2.4.0]: https://github.com/adelinferaru/nestedflowtracker/releases/tag/2.4.0
[2.3.0]: https://github.com/adelinferaru/nestedflowtracker/releases/tag/2.3.0
[2.2.0]: https://github.com/adelinferaru/nestedflowtracker/releases/tag/2.2.0
[2.1.0]: https://github.com/adelinferaru/nestedflowtracker/releases/tag/2.1.0
[2.0.0]: https://github.com/adelinferaru/nestedflowtracker/releases/tag/2.0.0
