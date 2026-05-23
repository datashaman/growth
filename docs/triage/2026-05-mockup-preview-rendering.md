# Mockup Preview Rendering

The project mockups page renders previews from stored `SpecMockupRevision` HTML
through the raw mockup route. Growth does not persist thumbnail images today:
each preview iframe loads the current revision's HTML, and optional theme CSS is
overlaid by adding a `theme` query parameter to the raw route.

The main initial-load cost is therefore the number and weight of sandboxed
iframe documents on the mockups page. The first improvement is to lazy-load
preview iframes and keep theme changes as URL-level preview overrides, not
browser-local durable state.

Default preview theme selection now comes from scoped theme assignments when a
mockup owner matches one, then falls back to the project default theme. A
temporary `theme=<slug>` query parameter previews another theme, and
`theme=none` disables theme overlay for that view.

MCP agents can inspect stored mockups through metadata and preview resources
without pulling screenshot bytes into JSON responses:

- `growth://mockups/{mockup}` returns metadata for the current revision.
- `growth://mockups/{mockup}/{revision}` returns metadata for a specific revision.
- `growth://mockups/{mockup}/{revision}/preview` returns the preview HTML.
- Mockup metadata exposes `screenshot.asset.url` for PNG pixels when
  pixel-level evidence is needed.

Metadata includes artifact references only. Agents should read metadata after
creating or refining mockups, inspect preview HTML for ordinary review, and use
the screenshot asset URL only when pixel-level evidence is needed.
