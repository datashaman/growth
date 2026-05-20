# MCP tools

Each class under `app/Mcp/Tools/**` is an MCP tool registered onto one or more
servers in `app/Mcp/Servers/*.php`. Tools are grouped by domain
(`Architecture/`, `Feedback/`, `Lint/`, …).

## Contract

- Extend `Laravel\Mcp\Server\Tool`.
- Implement `handle(Request $request, …): ResponseFactory` and
  `schema(JsonSchema $schema): array`.
- Implement `outputSchema(JsonSchema $schema): array` when the tool returns
  `Response::structured([...])` — output validation rejects drift against the
  declared shape. List/query tools that yield only `Response::text(...)` (e.g.
  `Projects/ListProjects.php`, `Requirements/ListRequirements.php`) can omit it.

## Attributes

- `#[Description('…')]` is mandatory — clients see it in `tools/list`.
- `#[IsReadOnly]` for queries (no DB writes, no transitions). See
  `app/Mcp/Tools/Projects/ListProjects.php`.
- `#[IsDestructive(false|true)]` for write/transition tools. A status
  transition that's reversible is `IsDestructive(false)` — see
  `app/Mcp/Tools/Feedback/TriageFeedback.php:20`.

## Responses

- `Response::structured([...])` is the default for tools that declare an
  `outputSchema()` — the shape **must match** the schema or output validation
  rejects it.
- `Response::text(...)` is fine for tools that just stream a list/projection
  and have no structured shape worth validating.
- `Response::error('user-facing message')` for foreseeable failures (not found,
  illegal transition). Don't throw for these.
- Validate input with `$request->validate([...])` using Laravel rules.

## Workspace scoping

Writes scope by the active workspace, never trust ids from the request alone:

```php
$row = Model::query()
    ->where('workspace_id', app(WorkspaceContext::class)->requireId())
    ->find($data['id']);
```

See `TriageFeedback.php:32` for the pattern.

## Sampling

A tool that wants to ask the client's model for text accepts `Sampling` as a
second handle argument and wraps it in the gateway:

```php
public function handle(Request $request, Sampling $sampling): ResponseFactory
{
    $text = (new McpSamplingGateway($sampling))->requestText($prompt, 200, $systemPrompt);
    // $text === null when the client can't sample — degrade gracefully.
}
```

See `McpSamplingGateway.php:19` for the contract; `TriageFeedback.php:42` for
the call site. Never branch on whether sampling worked beyond "use the text
if non-null".
