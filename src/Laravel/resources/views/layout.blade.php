<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Flow')</title>
    <style>
        :root {
            --bg: #0f1419; --panel: #171c24; --panel-2: #1e242e; --line: #2a313c;
            --text: #e6e9ef; --muted: #8a93a2; --accent: #5b9dff;
            --ok: #3fb950; --failed: #f85149; --running: #8a93a2;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--text);
            font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .flow-header {
            display: flex; align-items: baseline; gap: 16px;
            padding: 16px 24px; border-bottom: 1px solid var(--line); background: var(--panel);
        }
        .flow-brand { font-weight: 700; color: var(--text); font-size: 16px; }
        .flow-main { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .muted { color: var(--muted); }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--line); }
        th { color: var(--muted); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        tr:hover td { background: var(--panel); }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 999px;
            font-size: 12px; font-weight: 600; text-transform: capitalize;
        }
        .badge.ok { background: rgba(63,185,80,.15); color: var(--ok); }
        .badge.failed { background: rgba(248,81,73,.15); color: var(--failed); }
        .badge.running { background: rgba(138,147,162,.15); color: var(--running); }
        .filters { display: flex; gap: 12px; align-items: end; margin-bottom: 20px; flex-wrap: wrap; }
        .filters label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
        .filters select, .filters button {
            background: var(--panel-2); color: var(--text); border: 1px solid var(--line);
            border-radius: 6px; padding: 7px 10px; font: inherit;
        }
        .filters button { cursor: pointer; }
        .pager { display: flex; gap: 12px; margin-top: 18px; align-items: center; }
        .empty { padding: 48px; text-align: center; color: var(--muted); }
        /* tree */
        .tree { margin-top: 20px; }
        .node > .row, .node-leaf {
            display: grid; grid-template-columns: 1fr 220px 90px; gap: 12px; align-items: center;
            padding: 7px 10px; border-radius: 6px; border-left: 3px solid var(--line);
            background: var(--panel);
        }
        .node, details.node { margin: 4px 0; }
        details.node > summary { list-style: none; cursor: pointer; }
        details.node > summary::-webkit-details-marker { display: none; }
        details.node > summary .name::before { content: '▸'; color: var(--muted); margin-right: 6px; }
        details.node[open] > summary .name::before { content: '▾'; }
        .node-leaf .name { margin-left: 18px; }
        .children { margin-left: 22px; border-left: 1px dashed var(--line); padding-left: 8px; }
        .row.ok, .node-leaf.ok { border-left-color: var(--ok); }
        .row.failed, .node-leaf.failed { border-left-color: var(--failed); }
        .row.running, .node-leaf.running { border-left-color: var(--running); }
        .name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bar { height: 8px; background: var(--panel-2); border-radius: 999px; overflow: hidden; }
        .bar > span { display: block; height: 100%; background: var(--accent); }
        .bar.failed > span { background: var(--failed); }
        .dur { text-align: right; color: var(--muted); }
    </style>
</head>
<body>
    <header class="flow-header">
        <a href="{{ route('flow.index') }}" class="flow-brand">NestedFlowTracker</a>
        @yield('header')
    </header>
    <main class="flow-main">
        @yield('content')
    </main>
</body>
</html>
