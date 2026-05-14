<style>
    :root {
        color-scheme: light dark;
        --bg: var(--color-background-primary, light-dark(#f7f8fa, #0f1419));
        --panel: var(--color-background-secondary, light-dark(#ffffff, #1a1f24));
        --panel-soft: var(--color-background-tertiary, light-dark(#f1f4f6, #232a30));
        --line: var(--color-border-primary, light-dark(#d9e0e5, #2d3640));
        --line-soft: var(--color-border-secondary, light-dark(#e9edf0, #242c33));
        --text: var(--color-text-primary, light-dark(#172026, #f0f3f5));
        --muted: var(--color-text-secondary, light-dark(#66737d, #94a0a8));
        --muted-2: var(--color-text-tertiary, light-dark(#8a969f, #6e7a82));
        --accent: light-dark(#147d64, #2dd3a4);
        --accent-soft: light-dark(#dff4ed, #163e34);
        --accent-fg: light-dark(#ffffff, #0f1419);
        --danger: light-dark(#b42318, #f87171);
        --danger-soft: light-dark(#fde7e4, #3f1d1d);
        --warn: light-dark(#9a6500, #f59e0b);
        --warn-soft: light-dark(#fff1cf, #3a2e0d);
        --ok: light-dark(#177245, #4ade80);
        --ok-soft: light-dark(#ddf5e7, #15331f);
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        background: var(--bg);
        color: var(--text);
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        font-size: 14px;
        line-height: 1.4;
    }

    button,
    input,
    select {
        font: inherit;
    }

    .field {
        display: grid;
        gap: 6px;
        color: var(--muted);
        font-size: 12px;
    }

    .select,
    .input {
        width: 100%;
        min-height: 38px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--panel);
        color: var(--text);
        padding: 0 10px;
    }

    .button {
        border: 1px solid var(--accent);
        border-radius: 6px;
        background: var(--accent);
        color: var(--accent-fg);
        cursor: pointer;
        min-height: 38px;
        padding: 0 12px;
    }

    .button:disabled {
        cursor: wait;
        opacity: .65;
    }

    .rows {
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--panel);
        overflow: hidden;
    }

    .row {
        align-items: center;
        border-top: 1px solid var(--line-soft);
        display: flex;
        gap: 12px;
        justify-content: space-between;
        min-height: 38px;
        padding: 8px 11px;
    }

    .row:first-child {
        border-top: 0;
    }

    .structured-row {
        align-items: flex-start;
        display: block;
    }

    .structured-main {
        display: block;
        font-weight: 700;
    }

    .structured-meta {
        color: var(--muted);
        display: flex;
        flex-wrap: wrap;
        gap: 6px 12px;
        margin-top: 4px;
    }

    .structured-body {
        color: var(--text);
        line-height: 1.5;
        margin: 5px 0 0;
        max-width: 82ch;
    }

    .kv b {
        color: var(--muted-2);
        font-size: 11px;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .kv.wide {
        flex-basis: 100%;
    }

    .kv.wide b {
        display: inline-block;
        min-width: 170px;
    }

    .pill {
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        padding: 3px 8px;
        text-transform: uppercase;
    }

    .pass,
    .success {
        background: var(--ok-soft);
        color: var(--ok);
    }

    .warn,
    .warning {
        background: var(--warn-soft);
        color: var(--warn);
    }

    .fail,
    .error {
        background: var(--danger-soft);
        color: var(--danger);
    }

    .pending,
    .blocked {
        background: var(--panel-soft);
        color: var(--muted);
    }

    .table {
        border-collapse: collapse;
        width: 100%;
    }

    .table th,
    .table td {
        border-top: 1px solid var(--line-soft);
        padding: 8px 11px;
        text-align: right;
    }

    .table th:first-child,
    .table td:first-child {
        text-align: left;
    }

    .table thead th {
        background: var(--panel-soft);
        border-top: 0;
        color: var(--muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .empty,
    .loading,
    .error-panel {
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--panel);
        color: var(--muted);
        padding: 16px;
    }

    .error-panel {
        background: var(--danger-soft);
        border-color: var(--danger);
        color: var(--danger);
    }

    .tree {
        border: 1px solid var(--line);
        border-radius: 6px;
        margin-top: 8px;
        overflow: hidden;
    }

    .tree summary {
        align-items: center;
        background: var(--panel-soft);
        cursor: pointer;
        display: flex;
        font-weight: 700;
        justify-content: space-between;
        list-style: none;
        padding: 8px 10px;
    }

    .tree summary::-webkit-details-marker {
        display: none;
    }

    .tree summary::after {
        color: var(--muted);
        content: "+";
        font-weight: 700;
    }

    .tree[open] summary::after {
        content: "-";
    }

    .tree-body {
        padding: 0;
    }

    .resource-doc {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 6px;
        margin-top: 18px;
        overflow: hidden;
        padding: 22px 26px;
    }

    .resource-doc h1,
    .resource-doc h2,
    .resource-doc h3 {
        color: var(--text);
        letter-spacing: 0;
        line-height: 1.2;
    }

    .resource-doc h1 {
        font-size: 24px;
        margin: 0 0 18px;
    }

    .resource-doc h2 {
        border-top: 1px solid var(--line-soft);
        font-size: 18px;
        margin: 24px 0 10px;
        padding-top: 18px;
    }

    .resource-doc h2:first-child {
        border-top: 0;
        margin-top: 0;
        padding-top: 0;
    }

    .resource-doc h3 {
        font-size: 15px;
        margin: 18px 0 8px;
    }

    .resource-doc p {
        color: var(--text);
        margin: 10px 0;
        max-width: 78ch;
    }

    .resource-doc ul {
        margin: 10px 0 16px;
        padding-left: 22px;
    }

    .resource-doc li {
        margin: 6px 0;
        max-width: 88ch;
    }

    .resource-doc code {
        background: var(--panel-soft);
        border: 1px solid var(--line-soft);
        border-radius: 4px;
        color: var(--text);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        font-size: 12px;
        padding: 1px 4px;
    }

    .resource-doc em {
        color: var(--muted);
    }

    .resource-doc a {
        color: var(--accent);
        text-decoration: none;
    }

    .resource-doc a:hover {
        text-decoration: underline;
    }
</style>
<script>
    // Shared MCP app helpers, attached to window so module scripts can pick them up.
    window.GrowthApp = (() => {
        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function title(value) {
            return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
        }

        function number(value) {
            return Number(value ?? 0).toLocaleString(undefined, { maximumFractionDigits: 1 });
        }

        function containsUlid(value) {
            return typeof value === 'string' && /[0-9A-HJKMNP-TV-Z]{26}/i.test(value);
        }

        function isIdentifierKey(key) {
            return key === 'id' || key.endsWith('_id');
        }

        function isMainTextKey(key) {
            return ['description', 'summary', 'text', 'body', 'content', 'details', 'rationale', 'notes'].includes(key);
        }

        function isIndicatorKey(key) {
            return ['status', 'severity', 'state', 'result', 'outcome', 'conclusion', 'readiness_status'].includes(key);
        }

        function isWideMetaKey(key) {
            return ['objective', 'expected_results', 'actual_results', 'scope', 'approach', 'pass_fail_criteria'].includes(key);
        }

        function indicatorClass(value) {
            const normalized = String(value).toLowerCase().replaceAll('_', '-');

            if (['pass', 'passed', 'success', 'successful', 'ready', 'clean', 'ok'].includes(normalized)) {
                return 'pass';
            }

            if (['warn', 'warning', 'caution', 'pending'].includes(normalized)) {
                return 'warn';
            }

            if (['fail', 'failed', 'failure', 'error', 'blocked', 'not-ready', 'timed-out', 'action-required'].includes(normalized)) {
                return 'fail';
            }

            return 'pending';
        }

        function parseToolPayload(result) {
            if (result.structuredContent) {
                return result.structuredContent;
            }

            const text = result.content?.[0]?.text;
            if (!text) {
                return null;
            }

            try {
                return JSON.parse(text);
            } catch {
                return null;
            }
        }

        function inlineMarkdown(value) {
            return escapeHtml(value)
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/_([^_]+)_/g, '<em>$1</em>')
                .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noreferrer">$1</a>');
        }

        function renderMarkdown(markdown) {
            const blocks = [];
            let listItems = [];

            const flushList = () => {
                if (listItems.length === 0) {
                    return;
                }
                blocks.push(`<ul>${listItems.join('')}</ul>`);
                listItems = [];
            };

            for (const rawLine of String(markdown).split('\n')) {
                const line = rawLine.trimEnd();

                if (line.trim() === '') {
                    flushList();
                    continue;
                }

                if (line.startsWith('### ')) {
                    flushList();
                    blocks.push(`<h3>${inlineMarkdown(line.slice(4))}</h3>`);
                    continue;
                }

                if (line.startsWith('## ')) {
                    flushList();
                    blocks.push(`<h2>${inlineMarkdown(line.slice(3))}</h2>`);
                    continue;
                }

                if (line.startsWith('# ')) {
                    flushList();
                    blocks.push(`<h1>${inlineMarkdown(line.slice(2))}</h1>`);
                    continue;
                }

                if (line.startsWith('- ')) {
                    listItems.push(`<li>${inlineMarkdown(line.slice(2))}</li>`);
                    continue;
                }

                flushList();
                blocks.push(`<p>${inlineMarkdown(line)}</p>`);
            }

            flushList();
            return blocks.join('');
        }

        return {
            escapeHtml,
            title,
            number,
            containsUlid,
            isIdentifierKey,
            isMainTextKey,
            isIndicatorKey,
            isWideMetaKey,
            indicatorClass,
            parseToolPayload,
            inlineMarkdown,
            renderMarkdown,
        };
    })();
</script>
