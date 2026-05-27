# CLAUDE.md

Guidance for working in this repository.

## What this is

`adelinferaru/nestedflowtracker` — a **Laravel package** for tracing/metering execution flows.
It measures the time between a start point and an end point in code and persists each
measurement as a node in a **tree** (nested set), so nested sub-flows are recorded with their
parent/child relationships. A flow can span multiple applications via a shared `tracker_id`.

This is a library/package (no app, no front-end). It is consumed by Laravel apps via Composer
and Laravel package auto-discovery.

## Layout

- `src/NestedFlowTracker.php` — the core service. All logic lives here as **static** methods
  (`startTrack`, `endTrack`, `getTrackerId`/`setTrackerId`, `startTimer`, timers, the tracks stack).
- `src/Models/FNTrack.php` — Eloquent model for the `fn_flow_tracks` table; uses
  `kalnoy/nestedset`'s `NodeTrait` to store the hierarchy.
- `src/Facades/NestedFlowTracker.php` — facade resolving the `nestedflowtracker` singleton.
- `src/NestedFlowTrackerServiceProvider.php` — registers the singleton, loads the migration,
  and publishes config + migrations (tags `nestedflowtracker.config`, `nestedflowtracker.migrations`).
- `src/config/nestedflowtracker.php` — config (env-driven, see below).
- `src/migrations/2019_11_12_173835_create_fn_flow_tracks_tables.php` — creates `fn_flow_tracks`.
- `tests/` — `orchestra/testbench` suite (`TestCase.php` base + tests). `phpstan.neon` +
  `phpstan-baseline.neon` for static analysis; `.github/workflows/ci.yml` for CI.

## How it works (the important mental model)

- `startTrack($name, $message, $settings)` starts a `microtime` timer, creates an `FNTrack` row,
  and **pushes it onto an internal static stack** (`$tracks_queue`).
- While a track is open, the next `startTrack` is appended as a **child of the track on top of the
  stack** (`appendToNode(...)`). This is what builds the nested tree — order of start/end calls
  defines the hierarchy.
- `endTrack($name, $settings)` **pops the stack**, computes duration, optionally updates
  message/result/context/user_id/tracker_id, and saves.
- `tracker_id` (a `hexdec(uniqid())`, cached in the session) groups all rows of one flow. For
  multi-app flows, pass `NestedFlowTracker::getTrackerId()` and the current node's `id` to the
  downstream app so it continues the same tree.

The stack is process-global static state — start/end calls must be balanced (LIFO) per request.

## Configuration (env vars)

- `FLOW_TRACKER_ACTIVE` — master switch. **Defaults to `0` (off)**; every public method
  early-returns unless this is `1`. Nothing is recorded until it's enabled.
- `FLOW_TRACKER_COMPONENT` — name of the current app/component (stored on each row).
- `FLOW_TRACKER_DB_CONNECTION` — DB connection to write to (`default`, or a named connection
  defined in `config/database.php`). The migration honors this same value.

## Commands

```bash
composer test        # PHPUnit 11 via orchestra/testbench (tests/)
composer analyse     # PHPStan (larastan) level 5, with baseline
```

Tests boot a real Laravel app via `orchestra/testbench` against an **in-memory SQLite** DB
(`tests/TestCase.php`). They therefore require the **`pdo_sqlite`** PHP extension. CI enables it
via setup-php; if your local PHP has it disabled, run:

```bash
php -d extension=sqlite3 -d extension=pdo_sqlite vendor/bin/phpunit
```

PHPStan runs at level 5 and is **clean with no baseline** — keep it that way; fix new findings
rather than suppressing them. (`src/config/*` is excluded as declarative data.)

## Conventions & constraints

- Targets PHP `>=7.1.3` and Laravel `5.5` through `10.x` — keep changes compatible across that
  range (avoid features newer than the minimum PHP/Laravel supported).
- Code style is enforced via StyleCI (`.styleci.yml`).
- The package is published to Packagist; treat the public API (`startTrack`/`endTrack`/
  `getTrackerId`/facade) as a stable contract — changing signatures is a breaking change.

## Known rough edges (verify before relying on them)

- The singleton scaffolding (`getInstance`/`$instance`/`__construct`/`__clone`/`__wakeup`) is
  dead — everything is static. Slated for removal in the Phase 3 redesign.
- `endTrack` closes tracks in **LIFO order**; the `$trackerName` argument only selects the timer
  for the duration, not which track is closed. Callers must balance start/end. (Documented in
  the method's docblock; a safer API is a Phase 3 goal.)
- All state lives in process-global statics (`$tracker_id`, `$timers`, `$tracks_queue`, …) — not
  safe across queues/Octane. Phase 3 will make it request-scoped/injectable.
