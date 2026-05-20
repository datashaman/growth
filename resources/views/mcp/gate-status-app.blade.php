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

            .main {
                min-width: 0;
                padding: 22px 28px 32px;
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
                text-transform: uppercase;
            }

            .toolbar {
                align-items: center;
                display: flex;
                gap: 12px;
                margin: 18px 0 12px;
            }

            .toolbar .spacer {
                flex: 1;
            }

            .gate-grid {
                display: grid;
                gap: 14px;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                margin-top: 6px;
            }

            .gate-card {
                border: 1px solid var(--line);
                border-radius: 8px;
                background: var(--panel);
                cursor: pointer;
                display: flex;
                flex-direction: column;
                gap: 10px;
                padding: 14px 16px;
                text-align: left;
            }

            .gate-card[aria-expanded="true"] {
                border-color: var(--accent);
                grid-column: 1 / -1;
            }

            .gate-card-head {
                align-items: center;
                display: flex;
                gap: 10px;
                justify-content: space-between;
            }

            .gate-card-title {
                font-size: 15px;
                font-weight: 700;
                letter-spacing: 0;
                margin: 0;
            }

            .gate-card-description {
                color: var(--muted);
                margin: 0;
                font-size: 13px;
            }

            .gate-card-counts {
                color: var(--muted);
                display: flex;
                font-size: 12px;
                gap: 14px;
            }

            .gate-card-counts strong {
                color: var(--text);
                font-weight: 700;
            }

            .gate-findings {
                border-top: 1px solid var(--line-soft);
                margin-top: 8px;
                padding-top: 10px;
            }

            .gate-findings .row {
                align-items: flex-start;
                flex-direction: column;
                gap: 4px;
            }

            .gate-findings .row .finding-rule {
                color: var(--muted-2);
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 11px;
                letter-spacing: .03em;
            }

            .gate-findings .row .finding-pill {
                align-self: flex-start;
            }

            @media (max-width: 860px) {
                .shell {
                    grid-template-columns: 1fr;
                }

                .rail {
                    border-right: 0;
                    border-bottom: 1px solid var(--line);
                }

                .topbar {
                    flex-direction: column;
                }
            }
        </style>
        <script type="module">
        createMcpApp(async (app) => {
            const { escapeHtml, title, indicatorClass } = window.GrowthApp;

            const state = {
                app,
                selectedProjectId: null,
                projects: [],
                gates: null,
                overallStatus: null,
                expanded: new Set(),
                loading: true,
                error: null,
            };

            const root = document.getElementById('gate-status');

            app.autoResize();

            app.onToolInput((params) => {
                const projectId = params?.arguments?.project_id ?? params?.project_id ?? null;
                if (projectId && projectId !== state.selectedProjectId) {
                    state.selectedProjectId = projectId;
                    refresh();
                }
            });

            app.onToolResult((params) => {
                const text = params?.content?.[0]?.text;
                if (!text) {
                    return;
                }

                try {
                    const result = JSON.parse(text);
                    if (result.project_id && result.project_id !== state.selectedProjectId) {
                        state.selectedProjectId = result.project_id;
                        refresh();
                    }
                } catch {
                    // non-JSON payload, ignore
                }
            });

            root.addEventListener('change', (event) => {
                if (event.target.id === 'project-picker') {
                    state.selectedProjectId = event.target.value || null;
                    state.expanded = new Set();
                    refresh();
                }
            });

            root.addEventListener('click', async (event) => {
                if (event.target.closest('[data-expand]')) {
                    await app.requestDisplayMode('fullscreen');
                    return;
                }

                if (event.target.closest('[data-refresh]')) {
                    refresh();
                    return;
                }

                const card = event.target.closest('[data-gate-id]');
                if (card) {
                    const id = card.dataset.gateId;
                    state.expanded.has(id) ? state.expanded.delete(id) : state.expanded.add(id);
                    render();
                }
            });

            await loadProjects();

            async function loadProjects() {
                state.loading = true;
                state.error = null;
                render();

                const result = await app.callServerTool({
                    name: 'list-projects',
                    arguments: { limit: 100 },
                });

                if (result.isError) {
                    state.error = result.content?.[0]?.text ?? 'Unable to load projects.';
                    state.loading = false;
                    render();
                    return;
                }

                const payload = parseToolPayload(result);
                state.projects = payload?.results ?? [];

                if (!state.selectedProjectId && state.projects.length > 0) {
                    state.selectedProjectId = state.projects[0].id;
                }

                if (state.selectedProjectId) {
                    await loadGates();
                } else {
                    state.loading = false;
                    render();
                }
            }

            async function refresh() {
                if (!state.selectedProjectId) {
                    state.loading = false;
                    render();
                    return;
                }

                await loadGates();
            }

            async function loadGates() {
                state.loading = true;
                state.error = null;
                render();

                const result = await app.callServerTool({
                    name: 'evaluate-readiness-gates',
                    arguments: { project_id: state.selectedProjectId },
                });

                if (result.isError) {
                    state.error = result.content?.[0]?.text ?? 'Unable to evaluate gates.';
                    state.gates = null;
                    state.overallStatus = null;
                    state.loading = false;
                    render();
                    return;
                }

                const payload = parseToolPayload(result);
                state.gates = payload?.gates ?? [];
                state.overallStatus = payload?.status ?? null;
                state.loading = false;
                render();
            }

            function parseToolPayload(result) {
                return window.GrowthApp.parseToolPayload(result);
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

                return `
                    <div class="brand">
                        <div class="eyebrow">Growth</div>
                        <div class="brand-row">
                            <h1>Gate Status</h1>
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
                `;
            }

            function content() {
                if (state.projects.length === 0) {
                    return emptyPanel('No projects', 'Create a project to see gate readiness.');
                }

                if (!state.selectedProjectId) {
                    return emptyPanel('Select a project', 'Pick a project from the sidebar to evaluate its gates.');
                }

                if (!state.gates) {
                    return emptyPanel('No gate data', 'Click refresh to evaluate gates.');
                }

                return `
                    ${header()}
                    <div class="toolbar">
                        <span>${state.gates.length} gates · click a card for findings</span>
                        <span class="spacer"></span>
                        <button class="button" type="button" data-refresh>Refresh</button>
                    </div>
                    <div class="gate-grid">${state.gates.map(card).join('')}</div>
                `;
            }

            function header() {
                const project = state.projects.find((p) => p.id === state.selectedProjectId);
                const status = state.overallStatus ?? 'pending';

                return `
                    <header class="topbar">
                        <div class="title">
                            <h2>${escapeHtml(project?.name ?? '')}</h2>
                            <p>Readiness gates and the findings that block them.</p>
                        </div>
                        <div class="status-box">
                            <span>Overall</span>
                            <strong class="${indicatorClass(status)}">${escapeHtml(status)}</strong>
                        </div>
                    </header>
                `;
            }

            function card(gate) {
                const expanded = state.expanded.has(gate.id);
                const findings = expanded ? findingsList(gate.findings ?? []) : '';

                return `
                    <button type="button" class="gate-card" data-gate-id="${escapeHtml(gate.id)}" aria-expanded="${expanded}">
                        <div class="gate-card-head">
                            <h3 class="gate-card-title">${title(gate.id)}</h3>
                            <span class="pill ${indicatorClass(gate.status)}">${escapeHtml(gate.status)}</span>
                        </div>
                        <p class="gate-card-description">${escapeHtml(gate.description ?? '')}</p>
                        <div class="gate-card-counts">
                            <span><strong>${gate.errors ?? 0}</strong> errors</span>
                            <span><strong>${gate.warnings ?? 0}</strong> warnings</span>
                        </div>
                        ${findings}
                    </button>
                `;
            }

            function findingsList(findings) {
                if (findings.length === 0) {
                    return '<div class="gate-findings"><div class="rows"><div class="row"><span>No findings.</span></div></div></div>';
                }

                const rows = findings.map((finding) => `
                    <div class="row">
                        <span class="finding-rule">${escapeHtml(finding.rule ?? 'unknown.rule')}</span>
                        <span>${escapeHtml(finding.message ?? '')}</span>
                        <span class="pill ${indicatorClass(finding.severity ?? 'pending')} finding-pill">${escapeHtml(finding.severity ?? '')}</span>
                    </div>
                `).join('');

                return `<div class="gate-findings"><div class="rows">${rows}</div></div>`;
            }

            function loadingPanel() {
                return '<div class="loading">Loading gates...</div>';
            }

            function emptyPanel(label, hint) {
                return `<div class="empty"><strong>${escapeHtml(label)}</strong><br>${escapeHtml(hint ?? '')}</div>`;
            }

            function errorPanel() {
                return `<section class="error-panel">${escapeHtml(state.error)}</section>`;
            }
        });
        </script>
    </x-slot:head>

    <div id="gate-status"></div>
</x-mcp::app>
