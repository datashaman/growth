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
