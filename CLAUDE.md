# CLAUDE.md

Guidance for working in this repository.

## What this is

`adelinferaru/nestedflowtracker` — a **Laravel package** for tracing application-level execution
flows. You wrap a block of code in a *span*; the package times it and stores it as a row in a
flat table, with the parent/child structure recorded via `parent_span_id` and reconstructed at
read time. A flow can span multiple applications via a shared `trace_id`.

Positioning (see `ROADMAP.md`): **the zero-infra flow tracer for Laravel** — no collectors/backend
like OpenTelemetry, works in production, traces *your* business flows, and (in later phases) ships
its own viewer. We optimize for adoption: modern style, usefulness, ease of use. No backward-compat
constraint — nobody depends on this yet, so the API is designed clean.

Library/package only (no app, no front-end yet). Consumed via Composer + Laravel auto-discovery.

## Layout (3.0+)

The package is split into a **framework-agnostic Core** and a **Laravel adapter**. Both live in
the same Composer package today; users who don't want Laravel can still drive `Core\FlowTracker`
directly.

### `src/Core/` — pure PHP 8.1+, only PSR contracts

- `Core/FlowTracker.php` — the **instance-based** service. Holds per-flow state (open-spans stack,
  `traceId`, `userId`) and the API: `span()`, `start()`/`end()`, `fail()`, `currentSpan()`,
  `traceId()`/`setTraceId()`, `setUser()`, `enabled()`, `flush()`. Constructor takes
  `Core\FlowConfig`, a PSR-14 `EventDispatcherInterface`, and a `Core\Drivers\SpanDriver` — no
  Laravel-isms. Bound **scoped** by the Laravel provider.
- `Core/Span.php` — plain DTO that every driver, event, and the OTLP exporter operate on. Public
  properties mirror the `flow_spans` columns.
- `Core/FlowConfig.php` — readonly DTO with `enabled` (bool) and `component` (string). The Laravel
  provider builds one from `config('flow.*')`; non-Laravel callers construct it directly.
- `Core/Enums/SpanStatus.php` — `Running` / `Ok` / `Failed`.
- `Core/Events/SpanStarted.php`, `SpanFinished.php` — plain objects dispatched on open/close.
- `Core/TraceContext.php` — W3C `traceparent` value object (parse/build; our `trace_id` is 32-hex).
- `Core/Drivers/SpanDriver.php` — `opening(Span, ?Span)` / `closing(Span)` interface.
- `Core/Drivers/NullDriver.php` — discards spans.
- `Core/Drivers/LogDriver.php` — PSR-3 `LoggerInterface`-based structured log lines.
- `Core/Drivers/PdoDriver.php` + `BufferedPdoDriver.php` — framework-agnostic SQL drivers; the
  buffered variant bulk-inserts when the flow completes (open-span count returns to zero).
  `PdoSchema::create()` provisions the lean `flow_spans` table (sqlite/mysql/pgsql).
- `Core/Drivers/OtelDriver.php` — buffers in memory, calls `OtelExporter::exportSpans()` when
  the flow completes.
- `Core/Otel/OtelExporter.php` — PSR-18 client + PSR-17 factories. Builds OTLP-JSON, POSTs to
  `{endpoint}/v1/traces`. No OTel SDK dependency.
- `Core/Otel/OtelExporterConfig.php` — readonly DTO: endpoint, headers, serviceName.

### `src/Laravel/` — everything that depends on the framework

- `Laravel/FlowServiceProvider.php` — registers the scoped `FlowTracker` (constructing a `FlowConfig`
  from `config('flow.*')` and wrapping `events` in `Laravel\Bridge\PsrEventDispatcher` for PSR-14),
  resolves the active `SpanDriver` from `flow.driver` (+ `flow.buffer`), binds the PSR-18 client
  (Guzzle by default) + PSR-17 factories (Guzzle's `HttpFactory`, overridable), wires the
  `Http::withFlowTrace()` macro, opt-in HTTP middleware and queue listeners, viewer routes, and
  artisan commands. Boot loads migrations, views, and publishes `flow-config`/`flow-migrations`/
  `flow-views`.
- `Laravel/Facades/Flow.php` — the `Flow` facade (with `@method` docblocks for IDE support).
- `Laravel/helpers.php` — the `flow()` helper (autoloaded via composer `files`).
- `Laravel/Bridge/PsrEventDispatcher.php` — adapts Laravel's dispatcher to PSR-14
  `EventDispatcherInterface` (one-line wrapper).
- `Laravel/Eloquent/FlowSpan.php` — Eloquent model for `flow_spans`. Casts `status` (enum),
  `context`/`result` (array), `duration` (float). Used by the viewer/console/Eloquent drivers
  for reads; writes go through the query builder so casts don't double-serialize. **Not** used by
  `Core\FlowTracker` (which only knows about `Core\Span`).
- `Laravel/Drivers/EloquentDatabaseDriver.php` — INSERT on `opening()`, `UPDATE WHERE span_id = ?`
  on `closing()`. Same shape as `Core\Drivers\PdoDriver`, just on Laravel's connection (so
  `flow.connection` and Eloquent's casts apply on the viewer side). No per-flow state.
- `Laravel\Drivers\EloquentBufferedDriver.php` — like the above but bulk-inserts the whole flow
  when it completes (`flow.buffer`). Spans aren't persisted until the flow completes.
- `Laravel/Http/Middleware/TrackRequest.php` — wraps each HTTP request in a root span (opt-in).
- `Laravel/Http/Middleware/Authorize.php` — guards the viewer (local env, or a `viewFlow` gate).
- `Laravel/Http/Controllers/FlowViewerController.php` — viewer `index` (recent flows) + `show` (tree).
- `Laravel/Http/Controllers/FlowApiController.php` — JSON read API (`api/flows`, `api/flows/{trace}`),
  in the viewer route group.
- `Laravel/Console/` — `flow:prune`, `flow:show {trace}`, `flow:benchmark` (overhead per driver).
- `Laravel/Otel/ExportTrace.php` — queued job that reads the flow back out of `flow_spans`,
  converts each row to a `Core\Span` POPO, and hands them to `Core\Otel\OtelExporter`.
- `Laravel/resources/views/` — Blade viewer UI (`layout`, `index`, `show`, `partials/span`); no
  build step.
- `Laravel/config/flow.php` — config (`enabled`, `component`, `connection`, `driver`, `buffer`,
  `auto.*`, `viewer.*`, `otel.*`).
- `Laravel/migrations/` — `create_flow_spans_table`, `add_otel_columns_to_flow_spans` (2.1:
  `span_id`, `started_at`), `add_parent_span_id_to_flow_spans` (2.2),
  `add_created_at_index_to_flow_spans` (2.3).

### `tests/`

- `tests/Core/` — pure PHPUnit tests for the framework-agnostic drivers (in-memory sqlite PDO, no
  testbench).
- `tests/` (root) — `orchestra/testbench` suite for the Laravel adapter (`tests/Fixtures/` holds
  job fixtures, `tests/Support/RecordingHttpClient.php` is a PSR-18 fake used by the OTel tests).
- `phpstan.neon` (level 6, no baseline); `.github/workflows/ci.yml`.

## How it works (the important mental model)

- **`Flow::span($name, $callback, $options)`** is the primary API (Laravel). It opens a span, runs the
  callback, and closes the span in a `finally` — so it's **exception-safe** and balanced by
  construction (no manual end needed). Returns the callback's value untouched. On a thrown
  exception it marks the span `Failed` (recording the exception in `result`) and rethrows.
- **`start()`/`end()`** are the manual escape hatch (LIFO). Each open span is pushed onto an
  instance stack **with its own start timestamp**, so duration is computed from the popped span's
  stored start — there is no name-based timer lookup (the old footgun is gone). `end()` takes no
  name; it always closes the innermost open span.
- Nesting: a span opened while another is open is appended as a **child of the current top span**
  and inherits its `trace_id`. `options['root'] = true` forces an independent root.
- **`trace_id`** (32-char hex, OTel-style) groups every span of one flow. It is held on the
  instance (no session coupling) — generated on the first root span, or set via `setTraceId()` /
  `options['trace_id']` to continue an inbound flow across apps.
- The service is bound with `$app->scoped(...)`, so each HTTP request / queued job gets a fresh
  instance and state is flushed between them under Octane.
- **Storage driver (`flow.driver`):** `Core\FlowTracker` builds an in-memory `Core\Span` and calls
  `$driver->opening()/closing()`. Parent linkage is by `parent_span_id` (16-hex). `database`
  persists via `Laravel\Drivers\EloquentDatabaseDriver` (enables viewer + commands + the DB OTel
  export); `log`/`null`/`otel` are emit-only. `flow.buffer` swaps in `EloquentBufferedDriver`
  (one bulk insert per flow on completion; spans not persisted until the flow completes). The
  framework-agnostic `Core\Drivers\PdoDriver` and `BufferedPdoDriver` are not wired into
  `flow.driver`; non-Laravel callers instantiate them directly.
- **Tree reads use `parent_span_id`, ordered by `started_at`** (viewer, `flow:show`, OTel export)
  — works uniformly for every driver because that's the only parent linkage in the schema.
- Disabled (`flow.enabled = false`): `span()` becomes a transparent pass-through (runs the
  callback, returns its value); `start()`/`end()` are no-ops; nothing is written.
- **Cross-app propagation (W3C Trace Context):** outbound via the `Http::withFlowTrace()` macro
  (injects `traceparent` from the current trace + span id); inbound is read automatically by
  `TrackRequest` (continues the upstream trace). `TraceContext` parses/builds the header.
- **OpenTelemetry export (opt-in):** with `flow.otel.enabled` + endpoint, a `Core\Events\SpanFinished`
  listener fires `Laravel\Otel\ExportTrace` (queued) when a *root* span closes. The job reads the
  trace out of `flow_spans`, converts the rows to `Core\Span` POPOs, and hands them to
  `Core\Otel\OtelExporter`, which builds OTLP-JSON and POSTs it via a PSR-18 client (Guzzle by
  default; substitute your own by binding `Psr\Http\Client\ClientInterface` in the container).
  Each span carries a 16-hex `span_id` and a microsecond `started_at` (added in 2.1) for correct
  OTLP ids/timing. The export job is excluded from queue auto-instrumentation.
- **Auto-instrumentation (opt-in):** with `flow.auto.http`, `TrackRequest` is appended to the
  web + api groups (via the HTTP kernel, so it survives the kernel's group sync) and opens a root
  span per request. With `flow.auto.queue`, the provider listens to `JobProcessing`/`JobProcessed`/
  `JobExceptionOccurred` and opens a root span per job (calling `flush()` first so each job is an
  isolated trace). Both default off → zero overhead unless enabled. The `#[Trace]` attribute and
  batched writes were deferred to later phases.

## Usage

```php
use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;

$user = Flow::span('register user', function ($span) use ($data) {
    $account = Flow::span('create account', fn () => Account::create($data));
    Flow::span('send welcome email', fn () => Mail::to($account)->send(new Welcome()));
    return $account;
});

// or the helper, or app(FlowTracker::class)
flow()->span('charge card', fn () => $gateway->charge($card));
```

## Configuration (env vars)

- `FLOW_ENABLED` — master switch (default `true`).
- `FLOW_COMPONENT` — name of this app/service, stored on every span (default `app`).
- `FLOW_DRIVER` — storage driver `database`|`log`|`null`|`otel` (default `database`);
  `FLOW_LOG_CHANNEL` for the log driver; `FLOW_BUFFER` for buffered bulk-insert (database).
- `FLOW_CONNECTION` — DB connection for `flow_spans` (null = default; or a named connection).
- `FLOW_AUTO_HTTP` / `FLOW_AUTO_QUEUE` — opt-in auto-instrumentation (default `false`).
- `FLOW_OTEL_ENABLED` / `FLOW_OTEL_ENDPOINT` — opt-in OTLP/HTTP export (default off).
- `FLOW_VIEWER` / `FLOW_VIEWER_PATH` — opt-in built-in viewer UI (default off; path `flow`).
  Access: allowed in `local`, else needs a `viewFlow` gate. Views always register (so PHPStan,
  which boots the provider via composer `extra.laravel.providers`, resolves `flow::` views);
  routes only when enabled. Controllers use `response()->view()` (not the `view()` helper) to
  sidestep larastan's `view-string` check on namespaced package views.

## Commands

```bash
composer test        # PHPUnit 11 via orchestra/testbench (tests/)
composer analyse     # PHPStan (larastan) level 6, no baseline
```

Tests boot a real Laravel app via `orchestra/testbench` against an **in-memory SQLite** DB
(`tests/TestCase.php`), so they require the **`pdo_sqlite`** extension (CI enables it via setup-php).
Because the tracker is a scoped binding, testbench's per-test app refresh isolates state — no manual
reset needed.

PHPStan runs at **level 6** and is **clean with no baseline** — keep it that way; fix new findings
rather than suppressing them. (`src/config/*` is excluded as declarative data.)

## Conventions & constraints

- Targets PHP `^8.1` and Laravel `^10|^11|^12|^13` (L13 needs PHP 8.3+). Use modern idioms (typed signatures, enums,
  readonly, constructor promotion, anonymous migrations).
- **Published on Packagist** (currently 2.5.x; see `changelog.md` / git tags). It follows SemVer
  now, so breaking changes need a new major; additive changes are minor, fixes are patch.
- **Commit/PR style — no tool attribution.** House style: commit messages and PR bodies describe
  the change and nothing else — no `Co-Authored-By` trailers for tools, no "Generated with …"
  footers. (Overrides the default Claude Code commit trailer.)
- Keep `composer test` and `composer analyse` (PHPStan level 6, no baseline) green on every change.
- **Verify on both Laravel 10 and 12 before releasing** — the support matrix's low end has bitten
  us more than once (e.g. `casts()` is L11+, `guzzlehttp/guzzle` only transitively present on L12).
  To run the L10 stack locally: `composer require "laravel/framework:^10.0" "orchestra/testbench:^8.0"
  --no-update && composer update`, run the suite, then restore.
- Workflow: branch → PR → CI green (Actions matrix) → merge. Releases are git tags (`x.y.z`) +
  a GitHub Release; Packagist updates from the tag automatically.
- The phased build history and remaining ideas live in `ROADMAP.md`. The roadmap as planned is
  fully shipped; new work would be fresh features/fixes.
