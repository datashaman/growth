# MCP resources

Two distinct resource shapes live under `app/Mcp/Resources/**`:

## Data resources (JSON payloads)

A read-only projection of domain data addressed by a URI template.

- `extends Laravel\Mcp\Server\Resource implements HasUriTemplate`
- `use App\Mcp\Resources\Concerns\ReturnsStructuredJson`
- Attributes: `#[Name('…')]`, `#[Description('…')]`,
  `#[MimeType('application/json')]`, `#[Uri('growth://…/{id}/…')]`
- `handle(Request $request): Response` returns
  `Response::structured($this->structured(...))`.

See `ReadinessResource.php`, `RequirementsResource.php`, `IntentResource.php`.

Eager-load before returning — fetching a resource should not N+1 the database.
Chain `->with([...])` on the query, then map to the structured payload.

## App resources (MCP UI)

A blade-rendered interactive app that the client renders inline.

- `extends Laravel\Mcp\Server\AppResource`
- Attributes: `#[Name('…')]`, `#[Description('…')]`, `#[Uri('ui://resources/…')]`,
  and `#[AppMeta]` (or override `resolvedAppMeta()` to refine).
- `handle(Request $request): Response` returns
  `Response::view('mcp.<blade-name>', ['title' => $this->title(), …])`.

See `RequirementExplorerApp.php`, `GateStatusApp.php`,
`ProjectDashboardApp.php`. The matching blades live under
`resources/views/mcp/`; conventions for those blades are in that directory's
`CLAUDE.md`.

## Don't mix

A data resource MIME-types as JSON and is consumed by clients programmatically.
An app resource MIME-types as `text/html;profile=mcp-app` and is consumed by
clients visually. Don't add `ReturnsStructuredJson` to an `AppResource` or
return a view from a data resource.
