# NestedFlowTracker — 2.0 Roadmap

Living document for the upgrade effort. We work top-down: finish a phase (or a coherent slice)
before moving on. Check items off as they land.

## Product vision (the north star)

> **The zero-infra flow tracer for Laravel — wrap any block of code, see it as a timed tree,
> across apps, stored in your own database.**

Telescope traces framework internals in dev; OpenTelemetry needs collectors + a backend. Our
wedge is the gap between them: **no infrastructure, works in production, traces *your* logical
business flows, and ships with its own viewer.** We optimize for **adoption** — modern style,
usefulness, and ease of use.

The four pillars we build toward:
1. **An API people enjoy** — closure spans (`Flow::span('name', fn () => …)`) that auto-close and
   are exception-safe, killing the old LIFO start/end footgun by design. Helper + facade + DI.
2. **Auto-instrumentation** — HTTP/queue middleware (a root span per request/job) and a
   `#[Trace]` attribute, so users get value with zero manual calls.
3. **A viewer** (the adoption driver) — a publishable, opt-in route rendering flows by trace id
   as a collapsible tree / mini flame-graph. This is the README screenshot that sells it.
4. **Interop** — W3C Trace Context (`traceparent`) propagation + an optional OpenTelemetry
   exporter, so it scales up without forcing anyone onto OTel infra.

**Direction (agreed):**
- **Modernize, drop legacy** — PHP **8.1+**, Laravel **10 / 11 / 12**.
- **No backward-compatibility constraint** — nobody depends on this yet, so we redesign the
  public API freely and pick clean, conventional names (`Flow`, `flow()`, `trace_id`, `FlowSpan`).
- **Tests track the design** — Phases 1–2 used characterization tests to refactor safely; from
  Phase 3 on, tests specify the *new* behavior.

---

## Phase 0 — Foundation & tooling
Set up the scaffolding that makes every later phase safe and fast. Small, no behavior change.

- [x] Create `tests/` with an orchestra/testbench harness so the package can boot Laravel +
      an in-memory SQLite DB in tests. (`tests/TestCase.php`, `tests/SmokeTest.php`)
- [x] Wire `composer test` end-to-end (PHPUnit 11; `phpunit.xml` updated to 10/11 schema). Green: 2/2.
- [x] Add **GitHub Actions CI** (`.github/workflows/ci.yml`): matrix PHP 8.1/8.2/8.3 × Laravel
      10/11/12 (invalid combos excluded), plus a static-analysis job.
- [x] Add **Larastan/PHPStan** at level 5 with a baseline (`phpstan.neon`, `phpstan-baseline.neon`);
      27 pre-existing findings captured to burn down in Phase 1/2.
- [ ] Confirm/Update code style tooling (StyleCI config exists; consider Pint for local runs).
- [x] Update `.gitignore` (build/, .phpunit.cache). Badges + `changelog.md` format: deferred.

> **Local note:** `composer test` needs the `pdo_sqlite` PHP extension. CI enables it via
> setup-php; the local Laragon PHP 8.2 php.ini was updated to enable `pdo_sqlite` + `sqlite3`.
> Verified green locally: `composer test` (2/2) and `composer analyse` (no errors).

## Phase 1 — Correctness & tests  *(first focus)*
Pin down what the code does today, then fix the clear bugs.

- [x] **Characterization tests** for current behavior (30 tests): nesting/tree via the
      start/end stack, `tracker_id` propagation, session interaction, active/inactive switch,
      duration capture, settings handling (message/result/context/user_id/parent_id).
      Process-global statics reset between tests via reflection.
- [x] Fix the **redundant/contradictory `tracker_id` logic** in `startTrack` — collapsed to a
      single clear resolution (explicit → static → session → new); behavior preserved.
- [x] Harden `endTrack` settings handling: array branch vs `is_scalar` + `(string)` cast (no
      more `trim()` on arbitrary types).
- [x] Fix the `starTrack` typo and broken array-key quotes in `readme.md` examples.
- [x] Decide & document the contract for **unbalanced start/end** calls: kept LIFO, documented
      explicitly in the `endTrack` docblock + a characterization test. Name-matching detection
      deferred to the Phase 3 API redesign.
- [x] Verify nested-set writes under sibling/nested combinations (covered by `NestingTest`).
- [x] **Bonus:** PHPStan level 5 brought to **zero findings, no baseline** (model `@property`
      docblocks, accurate `setTrackerId` param type, `new self()`, config excluded).

## Phase 2 — Modernization (drop legacy)
Now that behavior is pinned, raise the floor.

- [x] `composer.json` bump — done in Phase 0 (PHP `^8.1`, Laravel `^10|^11|^12`, testbench,
      PHPUnit 11, larastan).
- [x] Adopt modern PHP: parameter/return types across the public API, typed static props where
      safe (`$timers`, `$tracks_queue`, `$db_connection`), `: static` fluent setters on the
      model, flattened control flow, accurate `@property`/`@param` docblocks. (Constructor
      promotion / enums deferred to the Phase 3 instance-based redesign.)
- [x] Replace deprecated Laravel usage: migration converted to an anonymous class with `void`
      return types; `\Config::get(...)` → `config(...)`; provider drops the obsolete `$defer`
      and gains `void`/`array` return types. Verified on Laravel 12.
- [x] Remove dead code: the unused singleton scaffolding (`getInstance`/`$instance`/
      `__construct`/`__clone`/`__wakeup`) and commented blocks are gone.
- [x] **Bonus:** raised PHPStan to **level 6** (still clean, no baseline).

## Phase 3 — Modern core + span API  *(done)*
Pillar 1. The injectable, Octane-safe engine and the API people enjoy. **Scope: core only** —
middleware/attributes/viewer/OTel come in later phases.

- [x] **Injectable `FlowTracker` service** — all per-flow state (open-spans stack, `traceId`,
      `userId`) is instance state; no process-global statics. Config + event dispatcher injected.
- [x] **Container-scoped binding** (`$app->scoped`) so each request/job gets a fresh instance;
      verified that testbench's per-test refresh isolates state with no reflection reset.
- [x] **`span(name, closure)`** — opens/closes around the callback, **exception-safe** (`finally`),
      returns the value, marks failed spans (records the exception). The recommended API.
- [x] **Manual `start()`/`end()`** kept (LIFO, instance-based). Each span stores its own start
      time on the stack, so duration no longer depends on a name lookup — the old footgun is gone.
- [x] **Ergonomic surface:** `Flow` facade (`@method` docblocks) + `flow()` helper + DI.
- [x] **Events** `SpanStarted` / `SpanFinished`; **`SpanStatus`** enum (running/ok/failed).
- [x] Clean data model: `FlowSpan`, `flow_spans` table, `trace_id` (32-hex, OTel-style), `name`,
      `status`, JSON-cast `context`/`result`; `config/flow.php` (`enabled`, `component`,
      `connection`). Old `NestedFlowTracker` class/facade and the session coupling are gone.
- [x] New behavioral test suite (21 tests) specifying the above; PHPStan level 6 stays clean.
      CLAUDE.md + README rewritten for the new API.

## Phase 4 — Auto-instrumentation  *(core done)*
Pillar 2. Value with zero manual calls. **Scope this round: HTTP + queue** (opt-in via
`flow.auto.*`, default off). `#[Trace]` and batched writes deferred.

- [x] HTTP middleware (`TrackRequest`): a root span per request (method + route uri), records
      method/path/status in context, marked `failed` on 5xx; manual spans nest under it.
      Appended via the HTTP kernel so it survives the kernel's group sync to the router.
- [x] Queue auto-instrumentation via `JobProcessing`/`JobProcessed`/`JobExceptionOccurred`
      listeners: a root span per job, `flush()`ed so each job is an isolated trace; failed jobs
      recorded as `failed`.
- [x] Config toggles `flow.auto.http` / `flow.auto.queue` (default off → zero overhead).
- [x] Tests (HTTP 200/500 + nesting + opt-in gate; queue ok/failed/isolation) on Laravel 10 & 12;
      PHPStan level 6 clean. README + CLAUDE.md updated.
- [ ] *(deferred)* `#[Trace]` method attribute — needs a call-interception mechanism.
- [ ] *(deferred → Phase 6)* batch/defer DB writes (flush at end of request).

## Phase 5 — The viewer (adoption driver)  *(core done)*
Pillar 3. Make the data *visible*. **Scope this round: index + flow detail** (Blade + vanilla,
no build step). Read API + artisan commands deferred.

- [x] Opt-in, secured route + Blade UI: flow **detail** (`/flow/{trace}`) renders the trace as a
      collapsible tree (native `<details>`) with duration bars and failed-span highlighting.
- [x] **Index** (`/flow`): recent flows with component/status/duration, filter by component/status.
- [x] Auth: allowed in `local`, otherwise a `viewFlow` gate (`Authorize` middleware). Opt-in via
      `flow.viewer.enabled`; path/middleware configurable; views publishable (`flow-views`).
- [x] `FlowSpan::getConnectionName()` honors `flow.connection` so viewer reads hit the right DB.
- [x] Tests (index/show/filter, 404 unknown trace, 403 without gate, 404 when disabled) on
      Laravel 10 & 12; PHPStan level 6 clean. README + CLAUDE.md updated.
- [ ] *(deferred)* JSON read/query API.
- [x] artisan commands (`flow:show`, `flow:prune`) — delivered in Phase 6.

## Phase 6 — Interop & housekeeping  *(core done)*
Pillar 4. **Scope this round: W3C Trace Context propagation + artisan commands.** OTel exporter,
storage drivers, and batched writes deferred (see below).

- [x] **W3C Trace Context** (`TraceContext`): outbound `Http::withFlowTrace()` macro injects
      `traceparent`; inbound `TrackRequest` reads it and continues the upstream trace. Our 32-hex
      `trace_id` maps onto the W3C trace id directly.
- [x] **Artisan**: `flow:show {trace}` (colored tree) and `flow:prune --days` (plain delete).
- [x] Added `guzzlehttp/guzzle` to dev deps (Laravel's HTTP client needs it; was only transitively
      present on the Laravel 12 stack). Tests on Laravel 10 & 12; PHPStan level 6 clean. Docs updated.
- [x] **OpenTelemetry exporter** — delivered in **2.1** (see below).
- [ ] *(deferred)* Pluggable storage drivers (DB now; log/null/OTel later).
- [ ] *(deferred)* Performance: batched/deferred DB writes, index review, benchmarks.

## 2.1 — OpenTelemetry export  *(done)*
A lightweight, opt-in OTLP/HTTP exporter — **no OTel SDK dependency** (keeps the zero-infra ethos).

- [x] `OtelExporter` builds OTLP-JSON and POSTs to `{endpoint}/v1/traces` via the HTTP client.
- [x] Export on flow completion: a `SpanFinished` listener queues `ExportTrace` when a root span
      closes (excluded from queue auto-instrumentation so we don't trace our own exporter).
- [x] Migration adds `span_id` (16-hex) + microsecond `started_at` for correct OTLP ids/timing;
      the outbound `traceparent` now uses the real `span_id`.
- [x] `config('flow.otel')` (`enabled`/`endpoint`/`headers`/`timeout`/`queue`), opt-in (default off).
- [x] Tests on Laravel 10 & 12; PHPStan level 6 clean. README/CLAUDE updated.

### Still open for a future release
- Pluggable storage drivers (DB now; log/null/OTel-direct later).
- Performance: batched/deferred DB writes, index review, benchmarks.
- Viewer: JSON read/query API.

## Phase 7 — Release & launch
- [ ] Docs site / rich README with the viewer screenshot; quickstart.
- [ ] `changelog.md`, tag `2.0.0`, Packagist, announcement.

---

### How we'll work
Each iteration: pick the next unchecked item(s), implement on a branch, keep CI green, update
this file and `CLAUDE.md` if conventions change. Phases are ordered by dependency, not rigidly —
we can pull a small Phase 5 win forward if it's cheap, but Phase 1's safety net comes before Phase 3.
