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
  `traceId()`/`setTraceId()`, `setUser()`, `enabled()`, `flush()`. Bound **scoped** in the container.
- `src/Models/FlowSpan.php` — Eloquent model for `flow_spans`; `kalnoy/nestedset` `NodeTrait` for
  the tree; casts `status` (enum), `context`/`result` (array), `duration` (float).
- `src/Enums/SpanStatus.php` — `Running` / `Ok` / `Failed`.
- `src/Events/SpanStarted.php`, `SpanFinished.php` — dispatched on open/close; each carries the span.
- `src/Facades/Flow.php` — the `Flow` facade (with `@method` docblocks for IDE support).
- `src/helpers.php` — the `flow()` helper (autoloaded via composer `files`).
- `src/FlowServiceProvider.php` — registers the scoped `FlowTracker`, merges config, loads/publishes
  migrations + config (`flow-config`, `flow-migrations` tags).
- `src/config/flow.php` — config (`enabled`, `component`, `connection`).
- `src/migrations/2026_05_27_000000_create_flow_spans_table.php` — creates `flow_spans`.
- `tests/` — `orchestra/testbench` suite. `phpstan.neon` (level 6, no baseline); `.github/workflows/ci.yml`.

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
  instance and state is flushed between them under Octane. (Full middleware is Phase 4.)
- Disabled (`flow.enabled = false`): `span()` becomes a transparent pass-through (runs the
  callback, returns its value); `start()`/`end()` are no-ops; nothing is written.

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
- `FLOW_CONNECTION` — DB connection for `flow_spans` (null = default; or a named connection).

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
