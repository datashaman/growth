# MCP UI app blades

Every blade under `resources/views/mcp/**` is a standalone single-page MCP
app rendered by an `AppResource` (see `app/Mcp/Resources/CLAUDE.md`).

## Shell

```blade
<x-mcp::app :title="$title">
    <x-slot:head>
        @include('mcp.partials._app-shell')
        <style> /* per-app CSS */ </style>
        <script type="module">
        createMcpApp(async (app) => { /* … */ });
        </script>
    </x-slot:head>

    <div id="…root id…"></div>
</x-mcp::app>
```

`@include('mcp.partials._app-shell')` brings in the shared CSS variables and
`window.GrowthApp` JS helpers.

## State + render

State is a plain JS object the script mutates; `render()` rewrites
`root.innerHTML` from a template literal. No framework. No reactivity beyond
event listeners on `root`.

## Talking to the server

```js
const result = await app.callServerTool({
    name: 'list-projects',
    arguments: { limit: 100 },
});

if (result.isError) { /* handle */ return; }

const payload = window.GrowthApp.parseToolPayload(result);
state.projects = payload?.results ?? [];   // ← MATCH THE TOOL'S OUTPUT KEY
```

**The payload key must match what the tool actually returns.** `ListProjects`
returns `results`, not `projects`. Three blades had this exact bug (PR #334).
When in doubt, open the tool class and read its `Response::structured([...])`
call.

## Reacting to the launching tool

When the user opens the app via a tool call, wire both hooks so the app
re-renders to whatever the host tool asked for:

```js
app.onToolInput((params) => { /* params.arguments.project_id, etc. */ });
app.onToolResult((params) => { /* JSON.parse(params.content[0].text) */ });
```

## Shared helpers (`window.GrowthApp`)

- `escapeHtml(str)` — always wrap user/tool data before injecting into a
  template literal.
- `title(slug)` — humanises identifiers for display.
- `indicatorClass(status)` — maps pass/warn/fail/pending to CSS classes used
  in the shared shell stylesheet.
- `parseToolPayload(result)` — extracts the structured JSON envelope from an
  MCP tool result.

## Don't

- Don't pull in heavy client libraries on every app (vis-network in the
  trace-graph app is the deliberate exception).
- Don't introduce a build step. These blades render server-side and ship as
  is — keep the JS plain.
- Don't reach into the host webapp's CSS — variables only, no `flux:*`
  components.
