# CLAUDE.md

Guidance for working in this repository.

## What this is

`adelinferaru/nestedflowtracker` — a **Laravel package** for tracing application-level execution
flows. You wrap a block of code in a *span*; the package times it and stores it as a node in a
**tree** (nested set), so nested sub-operations are recorded with their parent/child structure.
A flow can span multiple applications via a shared `trace_id`.

Positioning (see `ROADMAP.md`): **the zero-infra flow tracer for Laravel** — no collectors/backend
like OpenTelemetry, works in production, traces *your* business flows, and (in later phases) ships
its own viewer. We optimize for adoption: modern style, usefulness, ease of use. No backward-compat
constraint — nobody depends on this yet, so the API is designed clean.

Library/package only (no app, no front-end yet). Consumed via Composer + Laravel auto-discovery.

## Layout

- `src/FlowTracker.php` — the core **instance-based** service. Holds per-flow state (open-spans
  stack, `traceId`, `userId`) and the API: `span()`, `start()`/`end()`, `fail()`, `currentSpan()`,
  `traceId()`/`setTraceId()`, `setUser()`, `enabled()`, `flush()`. Bound **scoped**. Persistence is
  delegated to a `SpanDriver` (it no longer touches the DB directly).
- `src/Drivers/` — `SpanDriver` interface + `DatabaseDriver` (nested-set, full features),
  `LogDriver`, `NullDriver`, `OtelDriver` (buffers in memory, emits on root close). Active driver
  resolved from `flow.driver`.
- `src/Models/FlowSpan.php` — Eloquent model for `flow_spans`; `kalnoy/nestedset` `NodeTrait` for
  the tree; casts `status` (enum), `context`/`result` (array), `duration` (float).
- `src/Enums/SpanStatus.php` — `Running` / `Ok` / `Failed`.
- `src/Events/SpanStarted.php`, `SpanFinished.php` — dispatched on open/close; each carries the span.
- `src/Facades/Flow.php` — the `Flow` facade (with `@method` docblocks for IDE support).
- `src/helpers.php` — the `flow()` helper (autoloaded via composer `files`).
- `src/Http/Middleware/TrackRequest.php` — wraps each HTTP request in a root span (opt-in).
- `src/Http/Middleware/Authorize.php` — guards the viewer (local env, or a `viewFlow` gate).
- `src/Http/Controllers/FlowViewerController.php` — viewer `index` (recent flows) + `show` (tree).
- `src/resources/views/` — Blade viewer UI (`layout`, `index`, `show`, `partials/span`); no build step.
- `src/TraceContext.php` — W3C `traceparent` value object (parse/build; our trace_id is 32-hex).
- `src/Console/` — `flow:prune`, `flow:show {trace}`, `flow:benchmark` (overhead per driver).
- `src/Otel/OtelExporter.php` (builds OTLP-JSON, POSTs to `{endpoint}/v1/traces`) and
  `ExportTrace.php` (queued job exporting one completed flow). No OTel SDK dependency.
- `src/FlowServiceProvider.php` — registers the scoped `FlowTracker`, merges config, loads views +
  migrations, publishes config/migrations/views (`flow-config`/`flow-migrations`/`flow-views`),
  registers the `Http::withFlowTrace()` macro + artisan commands, and wires opt-in
  auto-instrumentation (HTTP middleware via the kernel's group + queue listeners) and viewer routes.
- `src/config/flow.php` — config (`enabled`, `component`, `connection`, `auto.*`, `viewer.*`).
- `src/migrations/` — `create_flow_spans_table`, `add_otel_columns_to_flow_spans` (2.1: `span_id`,
  `started_at`), `add_parent_span_id_to_flow_spans` (2.2), `add_created_at_index_to_flow_spans` (2.3).
- `tests/` — `orchestra/testbench` suite (`tests/Fixtures/` holds job fixtures). `phpstan.neon`
  (level 6, no baseline); `.github/workflows/ci.yml`.

## How it works (the important mental model)

- **`Flow::span($name, $callback, $options)`** is the primary API. It opens a span, runs the
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
- **Storage driver (`flow.driver`):** `FlowTracker` builds an in-memory `FlowSpan` and calls
  `$driver->opening()/closing()`. Parent linkage is by `parent_span_id` (16-hex), so non-DB drivers
  don't need row ids. `database` persists (nested-set; enables viewer + commands + the DB OTel
  export); `log`/`null`/`otel` are emit-only (those DB-backed features don't apply).
- Disabled (`flow.enabled = false`): `span()` becomes a transparent pass-through (runs the
  callback, returns its value); `start()`/`end()` are no-ops; nothing is written.
- **Cross-app propagation (W3C Trace Context):** outbound via the `Http::withFlowTrace()` macro
  (injects `traceparent` from the current trace + span id); inbound is read automatically by
  `TrackRequest` (continues the upstream trace). `TraceContext` parses/builds the header.
- **OpenTelemetry export (opt-in):** with `flow.otel.enabled` + endpoint, a `SpanFinished`
  listener fires `ExportTrace` (queued) when a *root* span closes; `OtelExporter` builds OTLP-JSON
  and POSTs it. Each span carries a 16-hex `span_id` and a microsecond `started_at` (added in 2.1)
  for correct OTLP ids/timing. The export job is excluded from queue auto-instrumentation.
- **Auto-instrumentation (opt-in):** with `flow.auto.http`, `TrackRequest` is appended to the
  web + api groups (via the HTTP kernel, so it survives the kernel's group sync) and opens a root
  span per request. With `flow.auto.queue`, the provider listens to `JobProcessing`/`JobProcessed`/
  `JobExceptionOccurred` and opens a root span per job (calling `flush()` first so each job is an
  isolated trace). Both default off → zero overhead unless enabled. The `#[Trace]` attribute and
  batched writes were deferred to later phases.

## Usage

```php
use AdelinFeraru\NestedFlowTracker\Facades\Flow;

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
  `FLOW_LOG_CHANNEL` for the log driver.
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

- Targets PHP `^8.1` and Laravel `^10|^11|^12`. Use modern idioms (typed signatures, enums,
  readonly, constructor promotion, anonymous migrations).
- No backward-compatibility obligation yet — prefer the clean design over preserving old shapes.
- Work proceeds in phases (`ROADMAP.md`); keep `composer test` + `composer analyse` green each step.
