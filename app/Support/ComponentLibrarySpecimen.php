<?php

namespace App\Support;

final class ComponentLibrarySpecimen
{
    /**
     * @return array{description:string,guidance:list<string>,components:list<array{group:string,name:string,class:string,purpose:string,snippet:string}>}
     */
    public static function contract(): array
    {
        return [
            'description' => 'Semantic HTML component library for Growth mockup generation.',
            'guidance' => [
                'Use class names listed here — theme raw_css already styles them for all project themes.',
                'Wrap page content in <main> or use the project layout slot (id="growth-content").',
                'For custom elements not covered by a class, use semantic CSS variables: var(--surface), var(--surface-muted), var(--elevation-1), var(--radius-default), var(--spacing-inner-default), etc.',
                'Read the components design-system mockup for a full visual specimen with code examples.',
            ],
            'components' => [
                // ── Layout primitives ─────────────────────────────────────────
                ['group' => 'layout', 'name' => 'Panel', 'class' => 'panel', 'purpose' => 'Primary surface container with border, background, radius, and padding.', 'snippet' => '<div class="panel"><div class="label">Heading</div><p>Body content.</p></div>'],
                ['group' => 'layout', 'name' => 'Card', 'class' => 'card', 'purpose' => 'Secondary/sidebar surface container. Same styles as panel.', 'snippet' => '<div class="card">Sidebar content.</div>'],
                ['group' => 'layout', 'name' => 'Stack', 'class' => 'stack', 'purpose' => 'Vertical grid with consistent gap. Wrap any sibling elements.', 'snippet' => '<div class="stack"><div class="panel">First</div><div class="panel">Second</div></div>'],
                ['group' => 'layout', 'name' => 'Metrics grid', 'class' => 'metrics', 'purpose' => 'Auto-fit responsive grid for metric tiles and cards (min ~180px per column).', 'snippet' => '<div class="metrics"><div class="metric"><strong class="num">42</strong><span>Label</span></div></div>'],
                ['group' => 'layout', 'name' => 'Cards grid', 'class' => 'cards', 'purpose' => 'Auto-fit responsive grid for card-sized items (alias for .metrics).', 'snippet' => '<div class="cards"><div class="card">...</div><div class="card">...</div></div>'],
                ['group' => 'layout', 'name' => 'Split layout', 'class' => 'layout', 'purpose' => 'Two-column layout: wider main area (1.2fr) + narrower aside (0.8fr). Stacks on mobile.', 'snippet' => '<div class="layout"><div class="panel">Main</div><aside class="card">Aside</aside></div>'],
                ['group' => 'layout', 'name' => 'Screen header', 'class' => 'screen-header', 'purpose' => 'Page-level header with kicker label and h1 title.', 'snippet' => '<header class="screen-header"><div class="label">Section</div><h1>Page title</h1></header>'],

                // ── Data display ──────────────────────────────────────────────
                ['group' => 'data', 'name' => 'Metric tile', 'class' => 'metric', 'purpose' => 'KPI tile: large number (.num) + supporting label. Use inside .metrics grid.', 'snippet' => '<div class="metric"><strong class="num">1,284</strong><span>Tickets sold</span></div>'],
                ['group' => 'data', 'name' => 'Data table', 'class' => 'data-table', 'purpose' => 'Compact table with styled header row and row dividers.', 'snippet' => '<table class="data-table"><thead><tr><th>Name</th><th>Status</th></tr></thead><tbody><tr><td>Item</td><td><span class="badge success">Active</span></td></tr></tbody></table>'],
                ['group' => 'data', 'name' => 'Badge', 'class' => 'badge', 'purpose' => 'Compact inline status label. Variants: default, .success (green), .warn (amber).', 'snippet' => '<span class="badge">Default</span> <span class="badge success">Active</span> <span class="badge warn">Pending</span>'],
                ['group' => 'data', 'name' => 'Status chip', 'class' => 'status', 'purpose' => 'Pill-shaped live/active indicator chip.', 'snippet' => '<span class="status">Live</span>'],
                ['group' => 'data', 'name' => 'Accent bar', 'class' => 'bar', 'purpose' => 'Prominent accent strip, typically at the top of a panel.', 'snippet' => '<div class="bar"></div>'],
                ['group' => 'data', 'name' => 'Spark strip', 'class' => 'spark', 'purpose' => 'Thin secondary accent strip; use as a subtle visual divider or indicator.', 'snippet' => '<div class="spark"></div>'],
                ['group' => 'data', 'name' => 'Ledger block', 'class' => 'ledger-block', 'purpose' => 'Monospace block for audit logs, financial data, or timeline entries.', 'snippet' => '<div class="ledger-block">2026-05-24  INV-001  Vendor Name  +R 3,500.00</div>'],
                ['group' => 'data', 'name' => 'Status colour', 'class' => 'status-ok / status-bad', 'purpose' => 'Colour-only classes applied to any text element. .status-ok = green, .status-bad = red.', 'snippet' => '<span class="status-ok">●</span> Healthy  <span class="status-bad">●</span> Error'],

                // ── Forms ─────────────────────────────────────────────────────
                ['group' => 'forms', 'name' => 'Text input', 'class' => 'label + input', 'purpose' => 'Standard labelled text field. Wrap label text and input together inside label.', 'snippet' => '<label>Field name <input type="text" value="Current value"></label>'],
                ['group' => 'forms', 'name' => 'Email / number input', 'class' => 'label + input', 'purpose' => 'Same pattern with type="email", type="number", etc.', 'snippet' => '<label>Email <input type="email" placeholder="user@example.com"></label>'],
                ['group' => 'forms', 'name' => 'Select', 'class' => 'label + select', 'purpose' => 'Labelled dropdown control.', 'snippet' => '<label>Status <select><option>Active</option><option>Pending</option></select></label>'],
                ['group' => 'forms', 'name' => 'Textarea', 'class' => 'label + textarea', 'purpose' => 'Multi-line text input. Resizable vertically.', 'snippet' => '<label>Notes <textarea rows="3">Existing content.</textarea></label>'],
                ['group' => 'forms', 'name' => 'Actions row', 'class' => 'actions', 'purpose' => 'Flex row of buttons. Primary, .secondary (outlined/muted), and .danger variants.', 'snippet' => '<div class="actions"><button type="button">Save</button><button type="button" class="secondary">Cancel</button><button type="button" class="danger">Delete</button></div>'],

                // ── Feedback ──────────────────────────────────────────────────
                ['group' => 'feedback', 'name' => 'Warning notice', 'class' => 'notice warn', 'purpose' => 'Non-critical warning or attention message.', 'snippet' => '<div class="notice warn">Warning message text.</div>'],
                ['group' => 'feedback', 'name' => 'Error notice', 'class' => 'notice error', 'purpose' => 'Blocking error or validation failure.', 'snippet' => '<div class="notice error">Error message text.</div>'],
                ['group' => 'feedback', 'name' => 'Success state', 'class' => 'success', 'purpose' => 'Positive confirmation message.', 'snippet' => '<div class="success">Success message text.</div>'],
                ['group' => 'feedback', 'name' => 'Muted text', 'class' => 'muted', 'purpose' => 'De-emphasised secondary text. Reduces visual weight for supporting detail.', 'snippet' => '<p class="muted">Supporting detail or timestamp.</p>'],
                ['group' => 'feedback', 'name' => 'Section kicker', 'class' => 'label', 'purpose' => 'Small uppercase label above a heading or section. Themed as muted.', 'snippet' => '<div class="label">Section kicker</div><h2>Section heading</h2>'],
            ],
        ];
    }

    public static function html(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
*, *::before, *::after { box-sizing: border-box; }
.cl-section-label {
  font-size: 11px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase;
  margin: 0 0 14px; padding-bottom: 10px; border-bottom: 1px solid rgba(128,128,128,.2); opacity: .55;
}
.cl-item { margin-bottom: 28px; }
.cl-item:last-child { margin-bottom: 0; }
.cl-item > h3 {
  font-size: 12px; font-weight: 800; font-family: ui-monospace, SFMono-Regular, monospace;
  margin: 0 0 3px;
}
.cl-item > .cl-desc { font-size: 12px; opacity: .6; margin: 0 0 10px; line-height: 1.5; }
.cl-preview {
  border: 1px dashed rgba(128,128,128,.25); border-radius: 6px; padding: 16px; margin-bottom: 6px;
}
.cl-code {
  background: rgba(128,128,128,.07); border-radius: 4px; padding: 9px 12px;
  font-family: ui-monospace, SFMono-Regular, monospace; font-size: 11px;
  white-space: pre; overflow-x: auto; color: inherit; opacity: .75;
}
</style>
</head>
<body>
<header class="screen-header">
  <div class="label">Design system</div>
  <h1>Component library</h1>
  <p class="muted">Semantic HTML components. Apply any project theme to preview themed output. Use these class names in mockup HTML — theme CSS styles them automatically.</p>
</header>

<div class="stack">

  <!-- ── LAYOUT PRIMITIVES ──────────────────────────────────────────── -->
  <div class="panel">
    <div class="cl-section-label">Layout primitives</div>

    <div class="cl-item">
      <h3>.panel / .card</h3>
      <p class="cl-desc">Primary surface container with border, background, radius, and padding. Use <code>.card</code> for secondary or sidebar areas.</p>
      <div class="cl-preview">
        <div class="layout">
          <div class="panel"><div class="label">Panel</div><p>Main content area.</p></div>
          <div class="card"><div class="label">Card</div><p>Sidebar content.</p></div>
        </div>
      </div>
      <pre class="cl-code">&lt;div class="panel"&gt;
  &lt;div class="label"&gt;Heading&lt;/div&gt;
  &lt;p&gt;Body content.&lt;/p&gt;
&lt;/div&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.stack</h3>
      <p class="cl-desc">Vertical grid with consistent gap. Wrap any sibling block elements to lay them out vertically.</p>
      <div class="cl-preview">
        <div class="stack">
          <div class="card"><p>First item</p></div>
          <div class="card"><p>Second item</p></div>
          <div class="card"><p>Third item</p></div>
        </div>
      </div>
      <pre class="cl-code">&lt;div class="stack"&gt;
  &lt;div class="card"&gt;First&lt;/div&gt;
  &lt;div class="card"&gt;Second&lt;/div&gt;
&lt;/div&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.metrics / .cards / .vendor-grid</h3>
      <p class="cl-desc">Auto-fit responsive grid. Columns expand to fill available width, minimum ~180 px each. Use for metric tiles, product cards, or vendor listings.</p>
      <div class="cl-preview">
        <div class="metrics">
          <div class="metric"><strong class="num">1,284</strong><span>Tickets sold</span></div>
          <div class="metric"><strong class="num status-ok">96%</strong><span>Capacity</span></div>
          <div class="metric"><strong class="num status-bad">3</strong><span>Errors</span></div>
        </div>
      </div>
      <pre class="cl-code">&lt;div class="metrics"&gt;
  &lt;div class="metric"&gt;
    &lt;strong class="num"&gt;1,284&lt;/strong&gt;
    &lt;span&gt;Tickets sold&lt;/span&gt;
  &lt;/div&gt;
&lt;/div&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.layout / .split</h3>
      <p class="cl-desc">Two-column layout: wider main area (1.2fr) + narrower aside (0.8fr). Stacks to single column on mobile.</p>
      <div class="cl-preview">
        <div class="layout">
          <div class="panel"><div class="label">Main</div><p>Primary content area with the wider column.</p></div>
          <div class="card"><div class="label">Aside</div><p>Sidebar with the narrower column.</p></div>
        </div>
      </div>
      <pre class="cl-code">&lt;div class="layout"&gt;
  &lt;div class="panel"&gt;Main content&lt;/div&gt;
  &lt;aside class="card"&gt;Sidebar&lt;/aside&gt;
&lt;/div&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.screen-header / header</h3>
      <p class="cl-desc">Page-level header section with kicker label, h1 title, and optional action row.</p>
      <div class="cl-preview">
        <header class="screen-header">
          <div class="label">Cape Town Jazz Festival 2026</div>
          <h1>Vendor dashboard</h1>
          <div class="actions">
            <button type="button">New application</button>
            <button type="button" class="secondary">Export</button>
          </div>
        </header>
      </div>
      <pre class="cl-code">&lt;header class="screen-header"&gt;
  &lt;div class="label"&gt;Section kicker&lt;/div&gt;
  &lt;h1&gt;Page title&lt;/h1&gt;
  &lt;div class="actions"&gt;...&lt;/div&gt;
&lt;/header&gt;</pre>
    </div>
  </div>

  <!-- ── DATA DISPLAY ───────────────────────────────────────────────── -->
  <div class="panel">
    <div class="cl-section-label">Data display</div>

    <div class="cl-item">
      <h3>.metric with .num</h3>
      <p class="cl-desc">KPI tile showing a large number and a label below. Use <code>.status-ok</code> or <code>.status-bad</code> on the number for colour signal.</p>
      <div class="cl-preview">
        <div class="metrics">
          <div class="metric"><strong class="num">1,284</strong><span>Tickets sold</span></div>
          <div class="metric"><strong class="num">42</strong><span>Vendors active</span></div>
          <div class="metric"><strong class="num status-ok">R 284,100</strong><span>Revenue</span></div>
          <div class="metric"><strong class="num status-bad">3</strong><span>Failed payments</span></div>
        </div>
      </div>
      <pre class="cl-code">&lt;div class="metric"&gt;
  &lt;strong class="num status-ok"&gt;R 284,100&lt;/strong&gt;
  &lt;span&gt;Revenue&lt;/span&gt;
&lt;/div&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.data-table / table</h3>
      <p class="cl-desc">Compact tabular data with a styled header row and row dividers. Combine with <code>.badge</code> cells for status columns.</p>
      <div class="cl-preview">
        <table class="data-table">
          <thead>
            <tr><th>Vendor</th><th>Category</th><th>Status</th><th>Revenue</th></tr>
          </thead>
          <tbody>
            <tr><td>Cape Jazz Grills</td><td>Food &amp; Beverage</td><td><span class="badge success">Active</span></td><td class="num">R 14,200</td></tr>
            <tr><td>Township Beats</td><td>Music</td><td><span class="badge warn">Pending</span></td><td class="num">R 8,400</td></tr>
            <tr><td>Artisan Market</td><td>Crafts</td><td><span class="badge">Inactive</span></td><td class="num">—</td></tr>
          </tbody>
        </table>
      </div>
      <pre class="cl-code">&lt;table class="data-table"&gt;
  &lt;thead&gt;
    &lt;tr&gt;&lt;th&gt;Name&lt;/th&gt;&lt;th&gt;Status&lt;/th&gt;&lt;th&gt;Amount&lt;/th&gt;&lt;/tr&gt;
  &lt;/thead&gt;
  &lt;tbody&gt;
    &lt;tr&gt;
      &lt;td&gt;Vendor&lt;/td&gt;
      &lt;td&gt;&lt;span class="badge success"&gt;Active&lt;/span&gt;&lt;/td&gt;
      &lt;td class="num"&gt;R 14,200&lt;/td&gt;
    &lt;/tr&gt;
  &lt;/tbody&gt;
&lt;/table&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.badge / .status</h3>
      <p class="cl-desc">Compact inline label chips. Badge variants: default (neutral), <code>.success</code> (green), <code>.warn</code> (amber). <code>.status</code> is a live/active pill chip.</p>
      <div class="cl-preview">
        <div class="actions">
          <span class="badge">Draft</span>
          <span class="badge success">Approved</span>
          <span class="badge warn">Pending review</span>
          <span class="badge error">Rejected</span>
          <span class="status">Live</span>
        </div>
      </div>
      <pre class="cl-code">&lt;span class="badge"&gt;Draft&lt;/span&gt;
&lt;span class="badge success"&gt;Approved&lt;/span&gt;
&lt;span class="badge warn"&gt;Pending&lt;/span&gt;
&lt;span class="status"&gt;Live&lt;/span&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.bar / .spark</h3>
      <p class="cl-desc"><code>.bar</code> is a prominent accent strip at the top of a panel. <code>.spark</code> is a thin secondary indicator strip.</p>
      <div class="cl-preview">
        <div class="panel">
          <div class="bar"></div>
          <div class="metrics">
            <div class="metric"><strong class="num">84%</strong><span>Completion</span></div>
            <div class="metric"><strong class="num">16</strong><span>Days left</span></div>
          </div>
          <div class="spark"></div>
        </div>
      </div>
      <pre class="cl-code">&lt;div class="panel"&gt;
  &lt;div class="bar"&gt;&lt;/div&gt;
  &lt;!-- panel content --&gt;
  &lt;div class="spark"&gt;&lt;/div&gt;
&lt;/div&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.ledger-block / .timeline / .audit</h3>
      <p class="cl-desc">Monospace block for financial data, audit logs, or timeline entries. Use <code>.num</code> on amounts for tabular figures.</p>
      <div class="cl-preview">
        <div class="ledger-block">
2026-05-24 10:42  INV-0042  Cape Jazz Grills     +R  3,500.00
2026-05-24 11:15  REF-0007  Township Beats        −R    420.00
2026-05-24 12:00  INV-0043  Artisan Market Co.   +R  1,200.00
────────────────────────────────────────────────────────────
                                       Balance   +R  4,280.00</div>
      </div>
      <pre class="cl-code">&lt;div class="ledger-block"&gt;
2026-05-24  INV-0042  Vendor  +R 3,500.00
&lt;/div&gt;</pre>
    </div>
  </div>

  <!-- ── FORMS ──────────────────────────────────────────────────────── -->
  <div class="panel">
    <div class="cl-section-label">Forms</div>

    <div class="cl-item">
      <h3>label + input</h3>
      <p class="cl-desc">Standard labelled text field. Wrap both the label text and the input inside a <code>&lt;label&gt;</code> element.</p>
      <div class="cl-preview">
        <div class="stack">
          <label>Event name <input type="text" value="Cape Town Jazz Festival 2026" aria-label="Event name"></label>
          <label>Contact email <input type="email" placeholder="organiser@example.com" aria-label="Contact email"></label>
          <label>Capacity <input type="number" value="5000" aria-label="Capacity"></label>
        </div>
      </div>
      <pre class="cl-code">&lt;label&gt;Field name
  &lt;input type="text" value="Current value"&gt;
&lt;/label&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>label + select</h3>
      <p class="cl-desc">Labelled dropdown select control.</p>
      <div class="cl-preview">
        <label>Application status
          <select aria-label="Application status">
            <option>Active</option>
            <option>Pending approval</option>
            <option selected>Under review</option>
            <option>Suspended</option>
          </select>
        </label>
      </div>
      <pre class="cl-code">&lt;label&gt;Status
  &lt;select&gt;
    &lt;option&gt;Active&lt;/option&gt;
    &lt;option&gt;Pending&lt;/option&gt;
  &lt;/select&gt;
&lt;/label&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>label + textarea</h3>
      <p class="cl-desc">Multi-line text input. Resizable vertically by the user.</p>
      <div class="cl-preview">
        <label>Vendor description
          <textarea aria-label="Vendor description" rows="3">Award-winning jazz venue and street food market in the heart of Cape Town's waterfront district.</textarea>
        </label>
      </div>
      <pre class="cl-code">&lt;label&gt;Description
  &lt;textarea rows="3"&gt;Existing content.&lt;/textarea&gt;
&lt;/label&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.actions — button row</h3>
      <p class="cl-desc">Flex row of buttons with wrapping. Use default for primary action, <code>.secondary</code> for outlined/muted actions, <code>.danger</code> for destructive actions.</p>
      <div class="cl-preview">
        <div class="actions">
          <button type="button">Save changes</button>
          <button type="button" class="secondary">Cancel</button>
          <button type="button" class="danger reject">Remove vendor</button>
        </div>
      </div>
      <pre class="cl-code">&lt;div class="actions"&gt;
  &lt;button type="button"&gt;Save&lt;/button&gt;
  &lt;button type="button" class="secondary"&gt;Cancel&lt;/button&gt;
  &lt;button type="button" class="danger"&gt;Delete&lt;/button&gt;
&lt;/div&gt;</pre>
    </div>
  </div>

  <!-- ── FEEDBACK ───────────────────────────────────────────────────── -->
  <div class="panel">
    <div class="cl-section-label">Feedback</div>

    <div class="cl-item">
      <h3>.notice.warn</h3>
      <p class="cl-desc">Non-critical warning or attention message. Use for things that need attention but are not blocking.</p>
      <div class="cl-preview">
        <div class="notice warn">Vendor approval is pending. The stall will not appear in the public market until an organiser approves the application.</div>
      </div>
      <pre class="cl-code">&lt;div class="notice warn"&gt;Warning message text.&lt;/div&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.notice.error</h3>
      <p class="cl-desc">Blocking error or validation failure. Use when the user must resolve an issue before continuing.</p>
      <div class="cl-preview">
        <div class="notice error">Payment processing failed. Please update your banking details and try again, or contact the festival organiser.</div>
      </div>
      <pre class="cl-code">&lt;div class="notice error"&gt;Error message text.&lt;/div&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.success</h3>
      <p class="cl-desc">Positive confirmation. Use after a successful action is completed.</p>
      <div class="cl-preview">
        <div class="success">Application submitted successfully. You will receive a confirmation email within 24 hours.</div>
      </div>
      <pre class="cl-code">&lt;div class="success"&gt;Success message text.&lt;/div&gt;</pre>
    </div>

    <div class="cl-item">
      <h3>.label / .muted</h3>
      <p class="cl-desc"><code>.label</code> renders small uppercase text for section kickers. <code>.muted</code> reduces opacity/colour for supporting or secondary detail.</p>
      <div class="cl-preview">
        <div class="stack">
          <div>
            <div class="label">Upcoming events</div>
            <p>Cape Town Jazz Festival 2026 — 15–18 May</p>
          </div>
          <p class="muted">Last updated 24 May 2026 at 12:00 UTC · Data refreshes every 5 minutes.</p>
        </div>
      </div>
      <pre class="cl-code">&lt;div class="label"&gt;Section kicker&lt;/div&gt;
&lt;p&gt;Primary content.&lt;/p&gt;
&lt;p class="muted"&gt;Supporting detail or timestamp.&lt;/p&gt;</pre>
    </div>
  </div>

</div>
</body>
</html>
HTML;
    }
}
