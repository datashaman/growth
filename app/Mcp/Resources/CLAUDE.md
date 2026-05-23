# MCP resources

Two distinct resource shapes live under `app/Mcp/Resources/**`:

## Data resources (JSON payloads)

A read-only projection of domain data addressed by a URI template.

- `extends Laravel\Mcp\Server\Resource implements HasUriTemplate`
- `use App\Mcp\Resources\Concerns\ReturnsStructuredJson`
- Attributes: `#[Name('…')]`, `#[Description('…')]`,
  `#[MimeType('application/json')]` (no `#[Uri]` — the URI template is a
  method, not an attribute).
- `public function uriTemplate(): UriTemplate` returns
  `new UriTemplate('growth://…/{id}/…')`.
- `handle(Request $request): Response` returns `$this->json([...])` — the
  trait wraps the array as `Response::text(json_encode(...))` with the JSON
  MIME type set by the attribute.

See `ReadinessResource.php`, `RequirementsResource.php`, `IntentResource.php`.

Eager-load before returning — fetching a resource should not N+1 the database.
Chain `->with([...])` on the query, then map to the structured payload.

### Mockup preview resources

Mockup preview resources are the browser-rendered inspection surface for stored
mockup HTML:

- `growth://mockups/{mockup}` returns the current revision preview.
- `growth://mockups/{mockup}/{revision}` returns a specific revision preview.
- `growth://mockups/{mockup}/{revision}/screenshot` returns PNG pixels.

Preview JSON stays lightweight: include visible text, metadata warnings, theme
metadata, and `screenshot.uri`/`screenshot.mime_type`; do not inline screenshot
bytes. Agents should read the preview URI after creating or changing a mockup,
and read the screenshot URI only when pixel-level visual evidence is needed.
Use `?theme=none` or `?theme={slug}` when previewing theme overrides.

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
