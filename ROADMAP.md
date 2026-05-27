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

- [ ] **Characterization tests** for current behavior: nesting/tree building via the
      start/end stack, `tracker_id` propagation, session interaction, the active/inactive switch,
      duration capture, and settings handling (message/result/context/user_id/parent_id).
- [ ] Fix the **redundant/contradictory `tracker_id` logic** in `startTrack` (lines ~110–127).
- [ ] Audit `endTrack` settings handling (the `trim($settings)` scalar branch) and harden types.
- [ ] Fix the `starTrack` typo and other errors in `readme.md` examples.
- [ ] Decide & document the contract for **unbalanced start/end** calls (currently silent
      LIFO pop) — at minimum test it; ideally detect mismatched timer names.
- [ ] Verify the nested-set writes are correct under sibling/nested combinations.

## Phase 2 — Modernization (drop legacy)
Now that behavior is pinned, raise the floor.

- [ ] `composer.json`: bump to `php: ^8.1`, `laravel/framework` (or `illuminate/*`)
      `^10 |^11 |^12`, `kalnoy/nestedset` to its current major; PHPUnit to 10/11.
- [ ] Adopt modern PHP: typed properties, parameter/return types, constructor promotion,
      `match`, readonly where sensible, first-class enums (e.g. for component/state if useful).
- [ ] Replace deprecated Laravel calls; verify migration API and `Config`/`Schema` usage
      against Laravel 12.
- [ ] Remove dead code (commented blocks, half-finished singleton scaffolding).

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
