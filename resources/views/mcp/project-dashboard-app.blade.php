<x-mcp::app :title="$title">
    <x-slot:head>
        @include('mcp.partials._app-shell')
        <style>
            .shell {
                min-height: 100vh;
                display: grid;
                grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
            }

            .rail {
                border-right: 1px solid var(--line);
                background: var(--panel);
                padding: 20px;
            }

            .brand {
                margin-bottom: 22px;
            }

            .eyebrow {
                color: var(--muted);
                font-size: 11px;
                font-weight: 700;
                letter-spacing: .08em;
                text-transform: uppercase;
            }

            .brand h1 {
                margin: 4px 0 0;
                font-size: 20px;
                letter-spacing: 0;
            }

            .brand-row {
                align-items: center;
                display: flex;
                gap: 8px;
                justify-content: space-between;
            }

            .expand-button {
                background: transparent;
                border: 1px solid var(--line);
                border-radius: 5px;
                color: var(--muted);
                cursor: pointer;
                font-size: 14px;
                line-height: 1;
                padding: 4px 7px;
            }

            .expand-button:hover {
                background: var(--panel-soft);
                color: var(--text);
            }

            .nav-list {
                margin-top: 18px;
                display: grid;
                gap: 2px;
            }

            .nav-divider {
                border: 0;
                border-top: 1px solid var(--line-soft);
                margin: 8px 0;
            }

            .resource-button {
                width: 100%;
                border: 0;
                border-radius: 5px;
                background: transparent;
                color: var(--muted);
                cursor: pointer;
                display: block;
                padding: 9px;
                text-align: left;
            }

            .resource-button:hover {
                background: var(--panel-soft);
                color: var(--text);
            }

            .resource-button.active {
                background: var(--accent-soft);
                color: var(--accent);
            }

            .main {
                min-width: 0;
                padding: 22px 28px 32px;
            }

            .page-head {
                padding-bottom: 14px;
            }

            .page-head h2 {
                margin: 0;
                font-size: 22px;
                letter-spacing: 0;
            }

            .topbar {
                align-items: start;
                border-bottom: 1px solid var(--line);
                display: flex;
                gap: 20px;
                justify-content: space-between;
                padding-bottom: 16px;
            }

            .title h2 {
                font-size: 24px;
                line-height: 1.15;
                margin: 0;
                letter-spacing: 0;
            }

            .title p {
                color: var(--muted);
                margin: 6px 0 0;
                max-width: 760px;
            }

            .status-box {
                border: 1px solid var(--line);
                border-radius: 6px;
                background: var(--panel);
                min-width: 154px;
                padding: 10px 12px;
            }

            .status-box span {
                color: var(--muted);
                display: block;
                font-size: 12px;
            }

            .status-box strong {
                display: block;
                font-size: 22px;
                margin-top: 1px;
            }

            .status-box strong.pass,
            .status-box strong.warn,
            .status-box strong.fail {
                background: transparent;
            }

            .metrics {
                display: grid;
                gap: 1px;
                grid-template-columns: repeat(5, minmax(120px, 1fr));
                margin: 18px 0;
                overflow: hidden;
                border: 1px solid var(--line);
                border-radius: 6px;
                background: var(--line-soft);
            }

            .metric {
                background: var(--panel);
                min-height: 70px;
                padding: 12px;
            }

            .metric span {
                color: var(--muted);
                display: block;
                font-size: 12px;
            }

            .metric strong {
                display: block;
                font-size: 24px;
                margin-top: 4px;
            }

            .grid {
                display: grid;
                gap: 16px;
                grid-template-columns: minmax(0, 1.1fr) minmax(0, .95fr);
            }

            .section {
                border-top: 1px solid var(--line);
                padding-top: 14px;
            }

            .section h3 {
                color: var(--muted);
                font-size: 12px;
                letter-spacing: .08em;
                margin: 0 0 8px;
                text-transform: uppercase;
            }

            .term-form {
                display: grid;
                gap: 8px;
                grid-template-columns: minmax(0, 1fr) auto;
                margin-bottom: 10px;
            }

            .term-entry {
                display: grid;
                gap: 3px;
            }

            .term-entry strong {
                font-size: 15px;
            }

            .term-entry p {
                color: var(--muted);
                margin: 0;
            }

            @media (max-width: 860px) {
                .shell {
                    grid-template-columns: 1fr;
                }

                .rail {
                    border-right: 0;
                    border-bottom: 1px solid var(--line);
                }

                .metrics,
                .grid {
                    grid-template-columns: 1fr;
                }

                .topbar {
                    flex-direction: column;
                }
            }
        </style>
        <script type="module">
        createMcpApp(async (app) => {
            const {
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
                renderMarkdown,
            } = window.GrowthApp;
            const state = {
                app,
                selectedProjectId: null,
                projects: [],
                project: null,
                page: 'dashboard',
                resource: null,
                term: {
                    query: '',
                    loading: false,
                    error: null,
                    result: null,
                },
                loading: true,
                error: null,
            };

            const root = document.getElementById('dashboard');

            app.autoResize();

            app.onToolInput((params) => {
                const projectId = params?.arguments?.project_id ?? params?.project_id ?? null;
                if (projectId && projectId !== state.selectedProjectId) {
                    state.selectedProjectId = projectId;
                    loadDashboard(projectId);
                }
            });

            app.onToolResult((params) => {
                const text = params?.content?.[0]?.text;
                if (!text) {
                    return;
                }

                const result = JSON.parse(text);
                if (result.project_id && result.project_id !== state.selectedProjectId) {
                    state.selectedProjectId = result.project_id;
                    loadDashboard(result.project_id);
                }
            });

            root.addEventListener('change', (event) => {
                if (event.target.id !== 'project-picker') {
                    return;
                }

                const projectId = event.target.value || null;
                state.selectedProjectId = projectId;
                loadDashboard(projectId);
            });

            root.addEventListener('click', async (event) => {
                const button = event.target.closest('[data-resource-uri]');
                if (button) {
                    await loadResource(button.dataset.resourceLabel, button.dataset.resourceUri);
                    return;
                }

                if (event.target.closest('[data-dashboard-page]')) {
                    state.page = 'dashboard';
                    state.resource = null;
                    render();
                    return;
                }

                if (event.target.closest('[data-expand]')) {
                    await app.requestDisplayMode('fullscreen');
                }
            });

            root.addEventListener('submit', async (event) => {
                if (!event.target.matches('[data-term-form]')) {
                    return;
                }

                event.preventDefault();
                await lookupTerm(new FormData(event.target).get('query'));
            });

            root.addEventListener('toggle', (event) => {
                const tree = event.target.closest('[data-tree-id]');
                if (!tree) {
                    return;
                }

                setTreeExpanded(tree.dataset.treeId, tree.open);
            }, true);

            await loadDashboard(state.selectedProjectId);

            async function loadDashboard(projectId) {
                state.loading = true;
                state.error = null;
                render();

                const args = projectId ? { project_id: projectId } : {};
                const result = await app.callServerTool({
                    name: 'get-project-dashboard-data',
                    arguments: args,
                });

                if (result.isError) {
                    state.error = result.content?.[0]?.text ?? 'Unable to load dashboard data.';
                    state.project = null;
                    state.loading = false;
                    render();
                    return;
                }

                const payload = JSON.parse(result.content[0].text);
                state.projects = payload.projects;
                if (!projectId && !payload.selected_project && payload.projects.length > 0) {
                    state.selectedProjectId = payload.projects[0].id;
                    state.loading = false;
                    render();
                    await loadDashboard(state.selectedProjectId);
                    return;
                }

                state.project = payload.selected_project;
                state.loading = false;
                render();
            }

            async function lookupTerm(query) {
                const trimmed = String(query ?? '').trim();
                state.term.query = trimmed;

                if (!trimmed) {
                    state.term.error = null;
                    state.term.result = null;
                    render();
                    return;
                }

                state.term.loading = true;
                state.term.error = null;
                render();

                const result = await app.callServerTool({
                    name: 'lookup-term',
                    arguments: {
                        query: trimmed,
                        limit: 5,
                    },
                });

                state.term.loading = false;

                if (result.isError) {
                    state.term.error = result.content?.[0]?.text ?? 'Unable to look up term.';
                    state.term.result = null;
                    render();
                    return;
                }

                const payload = parseToolPayload(result);
                if (payload) {
                    state.term.result = payload;
                    state.term.error = null;
                } else {
                    state.term.error = 'Unable to read lookup result.';
                    state.term.result = null;
                }

                render();
            }

            async function loadResource(label, uri) {
                state.page = 'resource';
                state.resource = {
                    label,
                    uri,
                    loading: true,
                    error: null,
                    text: '',
                    mimeType: '',
                };
                render();

                try {
                    const result = await app.readResource({ uri });
                    const content = result.contents?.[0] ?? {};
                    const parsed = parseResourceContent(content);

                    state.resource = {
                        label,
                        uri,
                        loading: false,
                        error: null,
                        text: content.text ?? content.blob ?? '',
                        mimeType: content.mimeType ?? '',
                        data: parsed.data,
                        structured: parsed.structured,
                    };
                } catch (error) {
                    state.resource = {
                        label,
                        uri,
                        loading: false,
                        error: error instanceof Error ? error.message : 'Unable to read resource.',
                        text: '',
                        mimeType: '',
                        data: null,
                        structured: false,
                    };
                }

                render();
            }

            function render() {
                root.innerHTML = `
                    <main class="shell">
                        <aside class="rail">
                            ${sidebar()}
                        </aside>
                        <section class="main">
                            ${state.error ? errorPanel() : ''}
                            ${state.loading ? loadingPanel() : content()}
                        </section>
                    </main>
                `;
            }

            function sidebar() {
                const options = state.projects.map((project) => `
                    <option value="${escapeHtml(project.id)}" ${project.id === state.selectedProjectId ? 'selected' : ''}>
                        ${escapeHtml(project.name)}
                    </option>
                `).join('');

                const resources = state.project ? resourcesList() : '';

                return `
                    <div class="brand">
                        <div class="eyebrow">Growth</div>
                        <div class="brand-row">
                            <h1>Project Dashboard</h1>
                            <button type="button" class="expand-button" data-expand title="Open fullscreen" aria-label="Open fullscreen">⛶</button>
                        </div>
                    </div>
                    <label class="field">
                        <span>Project</span>
                        <select id="project-picker" class="select">
                            <option value="">Select project</option>
                            ${options}
                        </select>
                    </label>
                    <div class="nav-list">
                        <button type="button" class="resource-button ${state.page === 'dashboard' ? 'active' : ''}" data-dashboard-page>
                            <span>Dashboard</span>
                        </button>
                        ${resources ? `<hr class="nav-divider">${resources}` : ''}
                    </div>
                `;
            }

            function content() {
                if (state.projects.length === 0) {
                    return emptyPanel('No projects');
                }

                if (!state.project) {
                    return emptyPanel('Select a project');
                }

                if (state.page === 'resource') {
                    return resourcePage();
                }

                return `
                    ${overview()}
                    <div class="metrics">${countMetrics()}</div>
                    <div class="grid">
                        <div>
                            ${readinessPanel()}
                            ${implementationPanel()}
                        </div>
                        <div>
                            ${termsPanel()}
                        </div>
                    </div>
                `;
            }

            function resourcePage() {
                const resource = state.resource;

                if (!resource) {
                    return emptyPanel('Resource');
                }

                const body = resource.loading
                    ? '<div class="loading">Loading resource...</div>'
                    : resource.error
                        ? `<section class="error-panel">${escapeHtml(resource.error)}</section>`
                        : `<article class="resource-doc">${resource.structured ? renderStructuredResource(resource.data) : renderMarkdown(resource.text)}</article>`;

                return `
                    <header class="page-head">
                        <div class="eyebrow">Resource</div>
                        <h2>${title(resource.label)}</h2>
                    </header>
                    ${body}
                `;
            }

            function overview() {
                const project = state.project;
                const readiness = project.readiness.status;

                return `
                    <header class="topbar">
                        <div class="title">
                            <h2>${escapeHtml(project.name)}</h2>
                            <p>${escapeHtml(project.description ?? '')}</p>
                        </div>
                        <div class="status-box">
                            <span>Readiness</span>
                            <strong class="${readiness}">${readiness}</strong>
                        </div>
                        <div class="status-box">
                            <span>Rigor level</span>
                            <strong>${project.rigor_level}</strong>
                        </div>
                    </header>
                `;
            }

            function countMetrics() {
                return Object.entries(state.project.counts).map(([label, value]) => `
                    <div class="metric">
                        <span>${title(label)}</span>
                        <strong>${value}</strong>
                    </div>
                `).join('');
            }

            function readinessPanel() {
                const readiness = state.project.readiness;
                const gates = readiness.gates.map((gate) => `
                    <div class="row">
                        <span>${title(gate.id)}</span>
                        <span class="pill ${gate.status}">${gate.status}</span>
                    </div>
                `).join('');

                return section('Readiness Gates', `
                    <div class="rows">${gates}</div>
                `);
            }

            function implementationPanel() {
                const summary = state.project.implementation.summary;
                return section('Implementation', metricList({
                    'Work items': summary.work_items,
                    'Delivery evidence': summary.with_delivery_evidence,
                    'Successful checks': summary.with_successful_checks,
                    'Failed checks': summary.with_failed_checks,
                    'Deployed': summary.deployed,
                    'Done without evidence': summary.done_without_delivery_evidence,
                }));
            }

            function termsPanel() {
                const term = state.term;
                const matches = term.result?.matches ?? [];
                const rows = matches.map((match) => `
                    <div class="row structured-row">
                        <div class="term-entry">
                            <strong>${escapeHtml(match.term)}</strong>
                            <p>${escapeHtml(match.body)}</p>
                        </div>
                    </div>
                `).join('');

                const body = term.error
                    ? `<div class="row"><span>${escapeHtml(term.error)}</span></div>`
                    : term.result
                        ? rows || '<div class="row"><span>No matching terms</span></div>'
                        : '<div class="row"><span>Enter a term to search the glossary.</span></div>';

                return section('Terms', `
                    <form class="term-form" data-term-form>
                        <input class="input" name="query" value="${escapeHtml(term.query)}" placeholder="Look up a term">
                        <button class="button" type="submit" ${term.loading ? 'disabled' : ''}>${term.loading ? 'Searching' : 'Lookup'}</button>
                    </form>
                    <div class="rows">${body}</div>
                `);
            }

            function resourcesList() {
                const flow = ['intent', 'requirements', 'architecture', 'plan', 'verification', 'evidence', 'readiness'];
                const links = flow
                    .filter((label) => state.project.resource_uris[label])
                    .map((label) => {
                        const uri = state.project.resource_uris[label];

                        return `
                            <button type="button" data-resource-label="${escapeHtml(label)}" data-resource-uri="${escapeHtml(uri)}" class="resource-button ${state.resource?.uri === uri ? 'active' : ''}">
                                <span>${title(label)}</span>
                            </button>
                        `;
                    }).join('');

                return links;
            }

            function metricList(metrics) {
                return Object.entries(metrics).map(([label, value]) => `
                    <div class="row">
                        <span>${label}</span>
                        <strong>${value}</strong>
                    </div>
                `).join('');
            }

            function section(label, body) {
                return `
                    <section class="section">
                        <h3>${label}</h3>
                        ${body}
                    </section>
                `;
            }

            function loadingPanel() {
                return '<div class="loading">Loading dashboard data...</div>';
            }

            function emptyPanel(label) {
                return `<div class="empty"><strong>${label}</strong><br>No dashboard data.</div>`;
            }

            function errorPanel() {
                return `<section class="error-panel">${escapeHtml(state.error)}</section>`;
            }

            function isStructuredContentObject(value) {
                if (!value || Array.isArray(value) || typeof value !== 'object') {
                    return false;
                }

                return ['name', 'title', 'label', 'heading'].some((key) => hasDisplayableValue(key, value[key]))
                    || Object.entries(value).some(([key, entry]) => isMainTextKey(key) && !Array.isArray(entry) && typeof entry !== 'object' && hasDisplayableValue(key, entry));
            }

            function hasDisplayableValue(key, value) {
                if (value === null || value === undefined || value === '') {
                    return false;
                }

                if (isIdentifierKey(key) || containsUlid(value)) {
                    return false;
                }

                if (Array.isArray(value)) {
                    return value.some((entry) => hasDisplayableValue(key, entry));
                }

                if (typeof value === 'object') {
                    return Object.entries(value).some(([entryKey, entry]) => hasDisplayableValue(entryKey, entry));
                }

                return true;
            }

            function displayablePayload(value) {
                if (value === null || value === undefined || value === '' || containsUlid(value)) {
                    return null;
                }

                if (Array.isArray(value)) {
                    const entries = value.map(displayablePayload).filter((entry) => entry !== null);

                    return entries.length ? entries : null;
                }

                if (typeof value === 'object') {
                    const entries = Object.fromEntries(Object.entries(value)
                        .filter(([key, entry]) => hasDisplayableValue(key, entry))
                        .map(([key, entry]) => [key, displayablePayload(entry)])
                        .filter(([, entry]) => entry !== null));

                    return Object.keys(entries).length ? entries : null;
                }

                return value;
            }

            function treeStorageKey() {
                const page = state.resource?.uri ?? state.page;

                return `growth.dashboard.expanded.${state.selectedProjectId ?? 'none'}.${page}`;
            }

            function treeState() {
                try {
                    return JSON.parse(localStorage.getItem(treeStorageKey()) ?? '{}');
                } catch {
                    return {};
                }
            }

            function isTreeExpanded(treeId) {
                return treeState()[treeId] === true;
            }

            function setTreeExpanded(treeId, expanded) {
                const next = treeState();
                next[treeId] = expanded;
                localStorage.setItem(treeStorageKey(), JSON.stringify(next));
            }

            function treeId(path) {
                return String(path).replace(/[^a-z0-9_.:-]/gi, '-');
            }

            function parseResourceContent(content) {
                if (content.mimeType === 'application/json' && content.text) {
                    try {
                        return {
                            structured: true,
                            data: JSON.parse(content.text),
                        };
                    } catch {
                        return {
                            structured: false,
                            data: null,
                        };
                    }
                }

                return {
                    structured: false,
                    data: null,
                };
            }

            function renderStructuredResource(data) {
                if (!data || typeof data !== 'object') {
                    return '<p>No structured data.</p>';
                }

                const body = renderStructuredBody(data);

                return body.trim() !== '' ? body : '<p><em>None captured.</em></p>';
            }

            function renderStructuredBody(data) {
                if (Array.isArray(data.sections)) {
                    return data.sections.map((section) => renderSection(section.title, section.items, `section.${section.title}`)).join('');
                }

                const ignored = new Set(['type', 'title', 'project', 'resource_uris']);

                return Object.entries(data)
                    .filter(([key]) => !ignored.has(key))
                    .map(([key, value]) => renderSection(title(key), value, key, key))
                    .join('');
            }

            function renderSection(label, value, path, key = label) {
                return `<h2>${escapeHtml(label)}</h2>${renderValue(value, path, key)}`;
            }

            function renderValue(value, path = 'root', key = '') {
                if (value === null || value === undefined || value === '') {
                    return '<p><em>None captured.</em></p>';
                }

                if (Array.isArray(value)) {
                    const items = value.map((item, index) => renderValueInline(item, `${path}.${index}`)).filter((item) => item.trim() !== '');

                    if (items.length === 0) {
                        return '<p><em>None captured.</em></p>';
                    }

                    return `<div class="rows">${items.map((item) => `<div class="row structured-row">${item}</div>`).join('')}</div>`;
                }

                if (typeof value === 'object') {
                    if (isStructuredContentObject(value)) {
                        return `<div class="rows"><div class="row structured-row">${renderValueInline(value, path)}</div></div>`;
                    }

                    return renderObjectSummary(value);
                }

                if (containsUlid(value)) {
                    return '<p><em>None captured.</em></p>';
                }

                return `<p>${renderInlineScalar(key, value)}</p>`;
            }

            function renderValueInline(value, path = 'item') {
                if (value === null || value === undefined || value === '') {
                    return '<span><em>None</em></span>';
                }

                if (Array.isArray(value)) {
                    const items = value.map((item, index) => renderValueInline(item, `${path}.${index}`)).filter((item) => item.trim() !== '');

                    return `<span>${items.length ? items.join('') : '<em>None</em>'}</span>`;
                }

                if (typeof value === 'object') {
                    const primary = [value.name, value.title, value.label, value.heading, value.version, value.environment, value.rule].find((entry) => entry && !containsUlid(entry)) ?? null;
                    const body = Object.entries(value)
                        .find(([key, entry]) => isMainTextKey(key) && !Array.isArray(entry) && typeof entry !== 'object' && hasDisplayableValue(key, entry) && entry !== primary)?.[1] ?? null;
                    const details = Object.entries(value)
                        .filter(([key, entry]) => !isMainTextKey(key) && !Array.isArray(entry) && typeof entry !== 'object' && entry !== primary && hasDisplayableValue(key, entry))
                        .slice(0, 4)
                        .map(([key, entry]) => renderMetaEntry(key, entry))
                        .join('');

                    const nested = Object.entries(value)
                        .filter(([key, entry]) => Array.isArray(entry) && hasDisplayableValue(key, entry))
                        .map(([key, entry]) => renderTree(key, entry, `${path}.${key}`))
                        .join('');

                    if (!primary && !body && !details && !nested) {
                        return '';
                    }

                    return `${primary ? `<span class="structured-main">${escapeHtml(primary)}</span>` : ''}${body ? `<p class="structured-body">${escapeHtml(body)}</p>` : ''}${details ? `<span class="structured-meta">${details}</span>` : ''}${nested}`;
                }

                if (containsUlid(value)) {
                    return '';
                }

                return `<span>${escapeHtml(value)}</span>`;
            }

            function renderObjectSummary(object) {
                const rows = Object.entries(object)
                    .filter(([key, value]) => hasDisplayableValue(key, value))
                    .map(([key, value]) => `
                        <div class="row">
                            <span>${title(key)}</span>
                            <strong>${Array.isArray(value) || typeof value === 'object' ? escapeHtml(JSON.stringify(displayablePayload(value))) : renderInlineScalar(key, value)}</strong>
                        </div>
                    `).join('');

                return rows ? `<div class="rows">${rows}</div>` : '<p><em>None captured.</em></p>';
            }

            function renderTree(label, value, path) {
                const id = treeId(path);
                const open = isTreeExpanded(id) ? ' open' : '';

                return `
                    <details class="tree" data-tree-id="${escapeHtml(id)}"${open}>
                        <summary><span>${title(label)}</span><span>${Array.isArray(value) ? value.length : ''}</span></summary>
                        <div class="tree-body">${renderValue(value, path)}</div>
                    </details>
                `;
            }

            function renderMetaEntry(key, value) {
                return `<span class="kv ${isWideMetaKey(key) ? 'wide' : ''}"><b>${title(key)}</b> ${renderInlineScalar(key, value)}</span>`;
            }

            function renderInlineScalar(key, value) {
                if (isIndicatorKey(key)) {
                    return `<span class="pill ${indicatorClass(value)}">${escapeHtml(value)}</span>`;
                }

                return escapeHtml(value);
            }

        });
        </script>
    </x-slot:head>

    <div id="dashboard"></div>
</x-mcp::app>
