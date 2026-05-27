# NestedFlowTracker — 2.0 Roadmap

Living document for the iterative upgrade effort. We work top-down: finish a phase (or a
coherent slice of it) before moving on. Check items off as they land.

**Direction (agreed):**
- **Modernize, drop legacy** — target PHP **8.1+** and Laravel **10 / 11 / 12**; drop PHP 7.x and Laravel 5–9.
- **Breaking changes allowed** — this is a **2.0**. We may redesign the API and internal state.
- **Correctness & tests first** — establish a safety net before refactoring.

**Guiding principle:** lock current behavior with characterization tests *before* rewriting,
so the big refactor (Phase 3) is done against a green suite rather than blind.

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

## Phase 3 — Architecture redesign (the 2.0 core)
The substantive refactor. Done against the green test suite from Phase 1.

- [ ] **Eliminate global mutable static state.** Convert `NestedFlowTracker` to a proper
      injectable, instance-based service resolved from the container (facade kept as a thin
      shim for ergonomics). The `$timers`/`$tracks_queue` stack becomes per-instance state.
- [ ] Reconsider the request-scoped lifecycle: the stack and `tracker_id` should be tied to a
      request/context, not process-global statics (important for queues/Octane/long-running workers).
- [ ] Design a cleaner public API for 2.0 (consider a fluent/`span()` + closure form that
      auto-closes, removing the need for balanced manual `endTrack` calls), while keeping
      `startTrack`/`endTrack` available.
- [ ] Make the model/table configurable; ensure custom DB connection path is first-class.
- [ ] Octane / long-running-process safety review.

## Phase 4 — Performance & optimization
- [ ] Reduce per-track overhead; make tracking near-zero-cost when inactive.
- [ ] Batch / defer DB writes (option to flush at end of request, or push to a queue) instead
      of a write per start and per end.
- [ ] Review nested-set write cost; index review on `fn_flow_tracks`.
- [ ] Benchmark before/after with a repeatable harness.

## Phase 5 — Features & enhancements
- [ ] Querying/reporting API to read back a flow tree (and render it) by `tracker_id`.
- [ ] Optional HTTP middleware to auto-start/stop a root track per request.
- [ ] Helpers to propagate `tracker_id`/parent across outbound HTTP calls (header convention).
- [ ] Pluggable storage drivers (DB now; log/null/others later).
- [ ] Artisan commands (e.g. prune old tracks, inspect a flow).

## Phase 6 — Release
- [ ] Full docs rewrite (README + upgrade guide 1.x → 2.0).
- [ ] Finalize `changelog.md`, tag `2.0.0`, update Packagist.
- [ ] Keep a maintenance `1.x` branch for legacy users.

---

### How we'll work
Each iteration: pick the next unchecked item(s), implement on a branch, keep CI green, update
this file and `CLAUDE.md` if conventions change. Phases are ordered by dependency, not rigidly —
we can pull a small Phase 5 win forward if it's cheap, but Phase 1's safety net comes before Phase 3.
