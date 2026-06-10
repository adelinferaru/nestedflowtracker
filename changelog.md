# Changelog

All notable changes to `nestedflowtracker` are documented here. This project follows
[Semantic Versioning](https://semver.org) and [Keep a Changelog](https://keepachangelog.com).

## [3.1.3] - 2026-06-10

### Changed
- **Lean dist archive.** `.gitattributes` now `export-ignore`s tests, docs, the demo/marketing
  assets, and tooling config, so `composer require` downloads only the package itself (~1.5 MB
  smaller). No code changes.

### Added
- `examples/plain-php.php` — a runnable round trip without Laravel: trace a checkout (including
  a failed span) with the framework-agnostic Core, store it via `BufferedPdoDriver` in SQLite,
  read the tree back with plain SQL. Linked from the README.
- `SECURITY.md` (private disclosure path, supported versions) and CI/PHP/license badges in the
  README.

## [3.1.2] - 2026-06-10

### Changed
- Metadata only: `composer.json` `homepage` now points to the landing page
  (https://adelinferaru.github.io/nestedflowtracker/), with `support` links for issues,
  source, and docs. No code changes.

## [3.1.1] - 2026-06-10

### Fixed
- **Buffered drivers no longer drop continuation flows.** `EloquentBufferedDriver`,
  `BufferedPdoDriver`, and `OtelDriver` now detect flow completion by the open-span count
  returning to zero instead of "the closed span has no parent". A flow continued via
  `options['parent_span_id']` — whose outermost span has a non-null parent — was previously
  buffered and then silently discarded. Buffers are also detached before writing/exporting,
  so a failed insert/POST can no longer replay stale spans into the next flow.
- **`flow.auto.queue` no longer wipes the caller's flow for sync-dispatched jobs.** The sync
  queue driver fires the same `JobProcessing`/`JobProcessed` events as a worker, and the
  listener used to `flush()` unconditionally — orphaning the surrounding request/job root
  span (stuck `running` forever, or the whole flow lost in buffered mode). Jobs dispatched
  inside an open flow now nest under the current span; the closing listeners close the job's
  own span (cleaning up spans the job leaked open) instead of blindly popping the innermost.
- **Viewer no longer recurses infinitely on pre-2.1 rows** with a NULL `span_id` (the null
  children lookup landed on the roots group, hanging the root under itself).
- **`Flow::end(['status' => …])` with an invalid value no longer half-closes the span** —
  the status override is validated before the stack is touched, so the `ValueError` leaves
  the span open and endable.
- **`Span::toRow()` can no longer emit `false` for unencodable `context`/`result`** —
  invalid UTF-8 is substituted and unencodable values degrade partially instead of failing
  the whole row (PDO binds `false` as `''`, which MySQL JSON columns reject).
- **`TraceContext::parse()` reads only the sampled bit** of the flags byte (`02`, the
  level-2 random-trace-id flag, no longer parses as sampled). **`TraceContext::spanId()`**
  passes 16-hex ids through and hashes non-numeric keys (uuid/ulid) instead of
  `(int)`-casting them into the W3C-invalid all-zero id.
- **The JSON API clamps `per_page` to 1–100** — a negative value previously dropped the
  LIMIT clause entirely and loaded the whole table.
- **The OTel export listener registers only for the `database` driver** (the export reads
  flows back out of `flow_spans`; with `log`/`null`/`otel` it queued pointless jobs), and
  re-checks `flow.otel.enabled` at fire time so `flow:benchmark` can disable it for its
  rolled-back throwaway flows.
- **Container-bound PSR-17 `RequestFactoryInterface`/`StreamFactoryInterface` are honored**
  by the OTLP exporter binding, as documented (previously only `ClientInterface` was).

## [3.1.0] - 2026-06-08

### Added
- **Laravel 13 support.** `laravel/framework` constraint is now `^10.0|^11.0|^12.0|^13.0`;
  `orchestra/testbench` (dev) gains `^11.0`. The CI matrix tests every PHP 8.3+ slot against L13.
- **`Core\Span::toRow()` and `Span::toRowMutable()`** — single source of truth for the flow_spans
  column shape and for `status` / `context` / `result` serialization. Used by every persistence
  driver. Closes the 4-way column-list duplication the 3.0 review flagged.
- **`options['parent_span_id']`** on `FlowTracker::start()` — attach a continuation span to a
  known parent by passing its 16-hex span_id. Replaces `options['parent_id']`.

### Changed (breaking — minor in practice)
- **Dropped `kalnoy/nestedset` from `require`.** The Eloquent `FlowSpan` model no longer uses the
  `NodeTrait`; the `_lft` / `_rgt` / `parent_id` columns are gone from the create migration. Every
  reader in the package (viewer, JSON API, console, OTel exporter) already walked the tree via
  `parent_span_id` since 2.4, so removing the nested-set bookkeeping has no behavioural impact on
  span recording or display. `EloquentDatabaseDriver` is now ~30 lines: insert on opening, update
  on closing, no `byId` model map. `EloquentBufferedDriver`'s row build drops the four dead column
  keys.
- **`options['parent_id']` is gone**, replaced by `options['parent_span_id']`. The new option sets
  `Span::$parent_span_id` directly, so continuation spans look exactly like normal nested children
  to every driver and to the OTel root-detection listener.
- **`Span::$parent_id` is gone** from the Core POPO — closes the altitude finding from the 3.0
  review. The framework-agnostic POPO now carries only framework-agnostic fields.

### Upgrade notes
There is no production-user upgrade path to document — this release ships against an empty install
base, so the create migration was edited in place rather than paired with a cleanup migration.
Anyone running 3.0.x against a real database can drop `_lft`, `_rgt`, and `parent_id` (the
`kalnoy/nestedset` columns) by hand; nothing in 3.1.0 reads or writes them.

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
