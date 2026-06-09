# NestedFlowTracker

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]

A **zero-infra flow tracer**. Wrap any block of code in a *span*; it gets timed and stored as a
tree in your own database, with nested sub-operations recorded as children. A single flow can span
multiple applications via a shared `trace_id`.

No collectors, no external backend — unlike OpenTelemetry you need no infrastructure, and unlike
Telescope it traces *your* business flows (not framework internals) and works in production.

![A checkout flow rendered as a timed tree in the built-in viewer](art/show.png)

**Requires** PHP 8.1+. As of **3.0** the package is split into a framework-agnostic **Core** (only
PSR-3/14/17/18 dependencies) and a **Laravel** adapter (auto-discovered on Laravel 10, 11, 12, or 13;
L13 needs PHP 8.3+). Use either side independently.

## Installation

```bash
composer require adelinferaru/nestedflowtracker
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="flow-migrations"
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag="flow-config"
```

### Upgrading from 2.x

3.0 is namespace-only: behaviour, config keys, env vars, the `flow_spans` schema and the artisan
commands are all unchanged. `composer update` plus a search-and-replace on imports usually does it.
The full namespace table lives in [`changelog.md`](changelog.md). The most common moves:

- `AdelinFeraru\NestedFlowTracker\Facades\Flow` → `…\Laravel\Facades\Flow`
- `AdelinFeraru\NestedFlowTracker\Models\FlowSpan` → `…\Laravel\Eloquent\FlowSpan`
- `AdelinFeraru\NestedFlowTracker\Events\SpanFinished` → `…\Core\Events\SpanFinished`
- `AdelinFeraru\NestedFlowTracker\TraceContext` → `…\Core\TraceContext`

## Usage

The recommended API is `span()`: it opens a span, runs your callback, and closes it automatically —
even if the callback throws. It returns the callback's value untouched.

```php
use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;

$account = Flow::span('register user', function () use ($data) {
    $account = Flow::span('create account', fn () => Account::create($data));

    Flow::span('send welcome email', fn () => Mail::to($account)->send(new Welcome()));

    return $account;
});
```

This records a tree:

```
register user .................. 142ms
├─ create account .............. 38ms
└─ send welcome email .......... 95ms
```

You can also use the `flow()` helper or resolve the service from the container:

```php
flow()->span('charge card', fn () => $gateway->charge($card));

app(\AdelinFeraru\NestedFlowTracker\Core\FlowTracker::class)->span(/* ... */);
```

### Without Laravel

Construct `Core\FlowTracker` yourself and drive it directly. Any PSR-3 logger / PSR-14 dispatcher
work; the package ships PDO-backed storage drivers, a PSR-18 OTLP exporter, and a `null` driver.

```php
use AdelinFeraru\NestedFlowTracker\Core\Drivers\PdoDriver;
use AdelinFeraru\NestedFlowTracker\Core\Drivers\PdoSchema;
use AdelinFeraru\NestedFlowTracker\Core\FlowConfig;
use AdelinFeraru\NestedFlowTracker\Core\FlowTracker;

$pdo = new PDO('sqlite:flows.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
PdoSchema::create($pdo);                                    // sqlite / mysql / pgsql

$flow = new FlowTracker(
    new FlowConfig(enabled: true, component: 'orders'),
    $events,                                                // any PSR-14 EventDispatcherInterface
    new PdoDriver($pdo),                                    // or BufferedPdoDriver for one bulk insert per flow
);

$flow->span('checkout', function ($span) use ($flow) {
    $flow->span('charge card', fn () => /* ... */);
});
```

Other Core drivers: `LogDriver(LoggerInterface)` (PSR-3), `NullDriver`, and `OtelDriver` (wraps the
PSR-18/17 `Core\Otel\OtelExporter`). All implement `Core\Drivers\SpanDriver` — bring your own if
you want a different backend.

### Enriching a span

The open span is passed to your callback:

```php
Flow::span('import csv', function ($span) use ($rows) {
    $span->context = ['rows' => count($rows)];
    $imported = $this->import($rows);
    $span->result = ['imported' => $imported];
    return $imported;
});
```

### Manual spans

When you cannot wrap the work in a closure, open and close spans manually (LIFO — the innermost
open span is closed first):

```php
Flow::start('long running process');
// ...
Flow::end(['result' => ['ok' => true]]);
```

### Across applications (W3C Trace Context)

Flows propagate across services via the standard [`traceparent`](https://www.w3.org/TR/trace-context/)
header (our `trace_id` is already a 32-hex W3C trace id).

**Outbound** — add the current trace to an HTTP client call:

```php
Http::withFlowTrace()->post('https://orders.internal/checkout', $payload);
```

**Inbound** — with `flow.auto.http` enabled, an incoming `traceparent` is read automatically and the
request's root span continues the upstream trace. Doing it manually:

```php
use AdelinFeraru\NestedFlowTracker\Core\TraceContext;

if ($ctx = TraceContext::parse($request->header('traceparent'))) {
    Flow::setTraceId($ctx->traceId);
}
```

## Artisan commands

```bash
php artisan flow:show {trace}   # print a flow as a tree
php artisan flow:prune --days=30 # delete flow spans older than N days
```

### Events

`SpanStarted` and `SpanFinished` are dispatched as spans open and close, so you can react to them
(e.g. log slow spans):

```php
use AdelinFeraru\NestedFlowTracker\Core\Events\SpanFinished;

Event::listen(function (SpanFinished $event) {
    if ($event->span->duration > 1.0) {
        Log::warning("Slow span: {$event->span->name} ({$event->span->duration}s)");
    }
});
```

### Automatic instrumentation

Opt in to record spans with **zero manual calls**:

```dotenv
FLOW_AUTO_HTTP=true    # a root span per HTTP request (web + api groups)
FLOW_AUTO_QUEUE=true   # a root span per queued job
```

- **HTTP:** every request gets a root span named like `GET users/{id}`, with the method, path and
  response status in its context; it's marked `failed` on a 5xx response or an exception. Any
  manual `Flow::span()` calls during the request automatically nest underneath it.
- **Queue:** every processed job gets a root span (`job: App\Jobs\...`); failed jobs are recorded
  as `failed`. Each job is an isolated trace.

Both default to off, so installing the package never silently writes spans.

## Viewer

A small built-in UI to browse recorded flows as timed trees — no build step, no assets to compile.
Enable it and visit `/flow`:

```dotenv
FLOW_VIEWER=true
```

- **Index** (`/flow`) — recent flows with their component, status and duration; filter by
  component/status.
- **Detail** (`/flow/{trace}`) — the flow rendered as a collapsible tree with duration bars and
  failed spans highlighted.

![The viewer index listing recent flows](art/index.png)

**Access control:** the viewer is reachable automatically in the `local` environment. In any other
environment you must define a `viewFlow` gate to grant access:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewFlow', fn ($user) => $user->isAdmin());
```

Publish the views to customize them: `php artisan vendor:publish --tag="flow-views"`.

### JSON API

The viewer also exposes a read API (same enable flag + `viewFlow` gate):

```
GET {path}/api/flows               # recent flows; ?component=, ?status=, ?per_page=, ?page=
GET {path}/api/flows/{trace}       # one flow as a nested span tree
```

```jsonc
// GET /flow/api/flows/{trace}
{ "trace_id": "…", "spans": [ { "name": "checkout", "status": "ok", "duration": 0.19,
  "children": [ { "name": "charge card", "status": "ok", "duration": 0.08, "children": [] } ] } ] }
```

For token-based/stateless API clients, set `flow.viewer.middleware` to `['api']`.

## Storage drivers

Choose where finished spans go with `flow.driver`:

| Driver | Stores spans as | Viewer / `flow:*` |
| --- | --- | --- |
| `database` (default) | a tree in your database | ✅ |
| `log` | structured log lines (`flow.log.channel`) | — |
| `null` | discarded (API stays on) | — |
| `otel` | sent straight to an OTLP collector, no DB | — |

```dotenv
FLOW_DRIVER=database   # database | log | null | otel
```

The viewer, the artisan commands, and the `flow.otel` export below are **database-only** features
(they read from the `flow_spans` table). The `log`, `null`, and `otel` drivers are emit-only.

## OpenTelemetry export

Already running an OpenTelemetry Collector, Jaeger, or Grafana Tempo? Ship completed flows there
too — no OTel SDK required, we just POST OTLP-JSON. When a flow's root span closes, the whole trace
is exported on a queue.

```dotenv
FLOW_OTEL_ENABLED=true
FLOW_OTEL_ENDPOINT=http://localhost:4318   # spans are sent to {endpoint}/v1/traces
```

This is the database path: spans are stored *and* exported. If you don't want to store them at
all, use the `otel` storage driver above (`FLOW_DRIVER=otel`), which sends spans straight to the
collector with no database.

> Upgrading from an earlier 2.x? Re-publish and run migrations after upgrading:
> `php artisan vendor:publish --tag="flow-migrations" && php artisan migrate`.
> Run a queue worker so exports happen off the request.

## Configuration

| Env | Config key | Default | Description |
| --- | --- | --- | --- |
| `FLOW_ENABLED` | `flow.enabled` | `true` | Master switch. When off, `span()` runs your callback transparently and stores nothing. |
| `FLOW_COMPONENT` | `flow.component` | `app` | Name of this application/service, stored on every span. |
| `FLOW_DRIVER` | `flow.driver` | `database` | Storage driver: `database` / `log` / `null` / `otel`. |
| `FLOW_BUFFER` | `flow.buffer` | `false` | Buffer a flow and bulk-insert on completion (database driver). |
| `FLOW_LOG_CHANNEL` | `flow.log.channel` | `null` | Log channel for the `log` driver (null = default). |
| `FLOW_CONNECTION` | `flow.connection` | `null` | Connection for the `flow_spans` table (null = default). |
| `FLOW_AUTO_HTTP` | `flow.auto.http` | `false` | Auto root span per HTTP request. |
| `FLOW_AUTO_QUEUE` | `flow.auto.queue` | `false` | Auto root span per queued job. |
| `FLOW_VIEWER` | `flow.viewer.enabled` | `false` | Register the built-in viewer routes. |
| `FLOW_VIEWER_PATH` | `flow.viewer.path` | `flow` | URL prefix for the viewer. |
| `FLOW_OTEL_ENABLED` | `flow.otel.enabled` | `false` | Export completed flows to an OTLP/HTTP collector. |
| `FLOW_OTEL_ENDPOINT` | `flow.otel.endpoint` | `null` | Collector base URL (spans go to `{endpoint}/v1/traces`). |

## Performance

Tracking costs nothing when off and little when on — measure it for your setup:

```bash
php artisan flow:benchmark --flows=300 --spans=5
```

Indicative per-span overhead (300 flows × 6 spans, in-memory SQLite — **your database and hardware
will differ**, the `database` figure especially):

| Scenario | µs / span |
| --- | --- |
| disabled (`flow.enabled=false`) | ~2 |
| `null` driver (tracking, no storage) | ~60 |
| `database` driver (immediate) | ~1030 |
| `database` driver (`flow.buffer=true`) | ~125 |

The immediate `database` cost is dominated by the two writes per span. **Buffered mode**
(`FLOW_BUFFER=true`) holds a whole flow in memory and bulk-inserts it in a single query when the
flow completes — roughly **8× faster** here. The trade-off: spans are only persisted once the
flow completes (a crash mid-flow loses it), so it's off by default. `flow_spans` is indexed on
`trace_id`, `span_id`, `component`, `status`, and `created_at`.

## Testing

```bash
composer test
composer analyse
```

## Credits

- [Feraru Ioan Adelin][link-author]
- [All Contributors][link-contributors]

## License

MIT. Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/adelinferaru/nestedflowtracker.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/adelinferaru/nestedflowtracker.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/adelinferaru/nestedflowtracker
[link-downloads]: https://packagist.org/packages/adelinferaru/nestedflowtracker
[link-author]: https://github.com/adelinferaru
[link-contributors]: ../../contributors
