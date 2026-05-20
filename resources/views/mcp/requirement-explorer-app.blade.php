<x-mcp::app :title="$title">
    <x-slot:head>
        @include('mcp.partials._app-shell')
        <style>
            .shell {
                min-height: 100vh;
                display: grid;
                grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
            }

            .rail {
                border-right: 1px solid var(--line);
                background: var(--panel);
                display: flex;
                flex-direction: column;
                gap: 14px;
                max-height: 100vh;
                overflow: hidden;
                padding: 20px;
            }

            .brand {
                margin-bottom: 4px;
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

            .filters {
                display: grid;
                gap: 10px;
            }

            .requirement-list {
                border: 1px solid var(--line);
                border-radius: 6px;
                background: var(--panel);
                display: flex;
                flex-direction: column;
                flex: 1;
                min-height: 0;
                overflow: hidden;
            }

            .requirement-list-head {
                border-bottom: 1px solid var(--line-soft);
                color: var(--muted);
                font-size: 12px;
                padding: 8px 11px;
            }

            .requirement-list-scroll {
                overflow-y: auto;
            }

            .requirement-row {
                background: transparent;
                border: 0;
                border-top: 1px solid var(--line-soft);
                color: var(--text);
                cursor: pointer;
                display: grid;
                gap: 4px;
                padding: 10px 12px;
                text-align: left;
                width: 100%;
            }

            .requirement-row:first-of-type {
                border-top: 0;
            }

            .requirement-row:hover {
                background: var(--panel-soft);
            }

            .requirement-row[aria-selected="true"] {
                background: var(--accent-soft);
            }

            .requirement-row-meta {
                color: var(--muted);
                display: flex;
                font-size: 11px;
                gap: 8px;
                text-transform: uppercase;
                letter-spacing: .04em;
            }

            .requirement-row-text {
                color: var(--text);
                font-size: 13px;
                line-height: 1.35;
            }

            .main {
                min-width: 0;
                overflow-y: auto;
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
                font-size: 22px;
                line-height: 1.2;
                margin: 0;
                letter-spacing: 0;
            }

            .title-meta {
                color: var(--muted);
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 8px;
            }

            .detail {
                display: grid;
                gap: 22px;
                margin-top: 18px;
            }

            .section h3 {
                font-size: 13px;
                font-weight: 700;
                letter-spacing: .04em;
                margin: 0 0 8px;
                text-transform: uppercase;
                color: var(--muted);
            }

            .acceptance {
                display: grid;
                gap: 8px;
            }

            .acceptance li {
                background: var(--panel);
                border: 1px solid var(--line);
                border-radius: 6px;
                list-style: none;
                padding: 10px 12px;
            }

            .acceptance ol {
                display: grid;
                gap: 8px;
                margin: 0;
                padding: 0;
            }

            .links-grid {
                display: grid;
                gap: 14px;
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }

            .links-card {
                background: var(--panel);
                border: 1px solid var(--line);
                border-radius: 6px;
                padding: 12px 14px;
            }

            .links-card h4 {
                color: var(--muted);
                font-size: 11px;
                font-weight: 700;
                letter-spacing: .06em;
                margin: 0 0 8px;
                text-transform: uppercase;
            }

            .links-card ul {
                display: grid;
                gap: 6px;
                font-size: 13px;
                margin: 0;
                padding: 0;
            }

            .links-card li {
                list-style: none;
            }

            .links-card .link-label {
                color: var(--text);
            }

            .links-card .link-id {
                color: var(--muted-2);
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 10px;
                margin-top: 2px;
            }

            .findings .row {
                align-items: flex-start;
                flex-direction: column;
                gap: 4px;
            }

            .findings .row .finding-rule {
                color: var(--muted-2);
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 11px;
                letter-spacing: .03em;
            }

            .findings .row .finding-pill {
                align-self: flex-start;
            }

            @media (max-width: 880px) {
                .shell {
                    grid-template-columns: 1fr;
                }

                .rail {
                    border-right: 0;
                    border-bottom: 1px solid var(--line);
                    max-height: none;
                }

                .topbar {
                    flex-direction: column;
                }
            }
        </style>
        <script type="module">
        createMcpApp(async (app) => {
            const { escapeHtml, title, indicatorClass } = window.GrowthApp;

            const RELATED_GROUPS = [
                { type: 'design_element', label: 'Design elements' },
                { type: 'test_case',      label: 'Test cases' },
                { type: 'test_plan',      label: 'Test plans' },
                { type: 'work_item',      label: 'Work items' },
                { type: 'source',         label: 'Sources' },
                { type: 'concern',        label: 'Concerns' },
                { type: 'requirement',    label: 'Related requirements' },
            ];

            const state = {
                app,
                projects: [],
                selectedProjectId: null,
                requirements: [],
                selectedRequirementId: null,
                filters: { layer: '', type: '', priority: '', q: '' },
                trace: null,
                findings: [],
                loadingProjects: true,
                loadingRequirements: false,
                loadingDetail: false,
                error: null,
            };

            const root = document.getElementById('requirement-explorer');

            app.autoResize();

            app.onToolInput((params) => {
                const projectId = params?.arguments?.project_id ?? params?.project_id ?? null;
                if (projectId && projectId !== state.selectedProjectId) {
                    state.selectedProjectId = projectId;
                    state.selectedRequirementId = null;
                    loadRequirements();
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
                        state.selectedRequirementId = null;
                        loadRequirements();
                    }
                } catch {
                    // ignore non-JSON
                }
            });

            root.addEventListener('change', (event) => {
                const target = event.target;
                if (target.id === 'project-picker') {
                    state.selectedProjectId = target.value || null;
                    state.selectedRequirementId = null;
                    state.trace = null;
                    state.findings = [];
                    loadRequirements();
                    return;
                }
                if (target.dataset?.filter) {
                    state.filters[target.dataset.filter] = target.value;
                    loadRequirements();
                }
            });

            root.addEventListener('input', (event) => {
                if (event.target.id === 'requirement-search') {
                    state.filters.q = event.target.value;
                    scheduleSearch();
                }
            });

            root.addEventListener('click', (event) => {
                if (event.target.closest('[data-expand]')) {
                    app.requestDisplayMode('fullscreen');
                    return;
                }
                const row = event.target.closest('[data-requirement-id]');
                if (row) {
                    const id = row.dataset.requirementId;
                    if (id !== state.selectedRequirementId) {
                        state.selectedRequirementId = id;
                        loadDetail();
                    }
                }
            });

            let searchTimer = null;
            function scheduleSearch() {
                if (searchTimer) {
                    clearTimeout(searchTimer);
                }
                searchTimer = setTimeout(() => {
                    searchTimer = null;
                    loadRequirements();
                }, 250);
            }

            await loadProjects();

            async function loadProjects() {
                state.loadingProjects = true;
                state.error = null;
                render();

                const result = await app.callServerTool({
                    name: 'list-projects',
                    arguments: { limit: 100 },
                });

                if (result.isError) {
                    state.error = result.content?.[0]?.text ?? 'Unable to load projects.';
                    state.loadingProjects = false;
                    render();
                    return;
                }

                const payload = window.GrowthApp.parseToolPayload(result);
                state.projects = payload?.results ?? [];
                state.loadingProjects = false;

                if (!state.selectedProjectId && state.projects.length > 0) {
                    state.selectedProjectId = state.projects[0].id;
                }

                if (state.selectedProjectId) {
                    await loadRequirements();
                } else {
                    render();
                }
            }

            async function loadRequirements() {
                if (!state.selectedProjectId) {
                    state.requirements = [];
                    state.findings = [];
                    state.trace = null;
                    render();
                    return;
                }

                state.loadingRequirements = true;
                state.error = null;
                render();

                const args = {
                    project_id: state.selectedProjectId,
                    limit: 200,
                };
                for (const key of ['layer', 'type', 'priority', 'q']) {
                    if (state.filters[key]) {
                        args[key] = state.filters[key];
                    }
                }

                const [requirementsResult, lintResult] = await Promise.all([
                    app.callServerTool({ name: 'list-requirements', arguments: args }),
                    app.callServerTool({
                        name: 'lint-project',
                        arguments: { project_id: state.selectedProjectId, sections: ['requirements'] },
                    }),
                ]);

                if (requirementsResult.isError) {
                    state.error = requirementsResult.content?.[0]?.text ?? 'Unable to load requirements.';
                    state.requirements = [];
                    state.loadingRequirements = false;
                    render();
                    return;
                }

                const requirementsPayload = window.GrowthApp.parseToolPayload(requirementsResult);
                state.requirements = requirementsPayload?.results ?? [];

                const lintPayload = !lintResult.isError ? window.GrowthApp.parseToolPayload(lintResult) : null;
                state.findings = lintPayload?.sections?.requirements ?? [];

                state.loadingRequirements = false;

                if (state.selectedRequirementId && !state.requirements.some((cap) => cap.id === state.selectedRequirementId)) {
                    state.selectedRequirementId = null;
                    state.trace = null;
                }

                if (!state.selectedRequirementId && state.requirements.length > 0) {
                    state.selectedRequirementId = state.requirements[0].id;
                    loadDetail();
                    return;
                }

                if (state.selectedRequirementId) {
                    loadDetail();
                    return;
                }

                render();
            }

            async function loadDetail() {
                if (!state.selectedRequirementId) {
                    state.trace = null;
                    render();
                    return;
                }

                state.loadingDetail = true;
                render();

                const result = await app.callServerTool({
                    name: 'trace-query',
                    arguments: { id: state.selectedRequirementId, depth: 2, direction: 'down' },
                });

                state.loadingDetail = false;

                if (result.isError) {
                    state.trace = { error: result.content?.[0]?.text ?? 'Unable to walk trace.' };
                    render();
                    return;
                }

                state.trace = window.GrowthApp.parseToolPayload(result) ?? { nodes: [], edges: [] };
                render();
            }

            function render() {
                root.innerHTML = `
                    <main class="shell">
                        <aside class="rail">${sidebar()}</aside>
                        <section class="main">
                            ${state.error ? errorPanel() : ''}
                            ${mainContent()}
                        </section>
                    </main>
                `;
            }

            function sidebar() {
                const projectOptions = state.projects.map((project) => `
                    <option value="${escapeHtml(project.id)}" ${project.id === state.selectedProjectId ? 'selected' : ''}>
                        ${escapeHtml(project.name)}
                    </option>
                `).join('');

                return `
                    <div class="brand">
                        <div class="eyebrow">Growth</div>
                        <div class="brand-row">
                            <h1>Requirements</h1>
                            <button type="button" class="expand-button" data-expand title="Open fullscreen" aria-label="Open fullscreen">⛶</button>
                        </div>
                    </div>
                    <label class="field">
                        <span>Project</span>
                        <select id="project-picker" class="select">
                            <option value="">Select project</option>
                            ${projectOptions}
                        </select>
                    </label>
                    <div class="filters">
                        ${filterSelect('layer', 'Layer', ['', 'stakeholder', 'system', 'software'])}
                        ${filterSelect('type', 'Type', ['', 'functional', 'performance', 'usability', 'interface', 'design_constraint', 'process', 'non_functional'])}
                        ${filterSelect('priority', 'Priority', ['', 'high', 'medium', 'low'])}
                        <label class="field">
                            <span>Search</span>
                            <input id="requirement-search" class="input" type="search" placeholder="Substring match" value="${escapeHtml(state.filters.q)}">
                        </label>
                    </div>
                    ${requirementList()}
                `;
            }

            function filterSelect(key, label, options) {
                const value = state.filters[key] ?? '';
                const opts = options.map((option) => `
                    <option value="${escapeHtml(option)}" ${option === value ? 'selected' : ''}>
                        ${option === '' ? 'All' : title(option)}
                    </option>
                `).join('');
                return `
                    <label class="field">
                        <span>${escapeHtml(label)}</span>
                        <select class="select" data-filter="${escapeHtml(key)}">${opts}</select>
                    </label>
                `;
            }

            function requirementList() {
                if (!state.selectedProjectId) {
                    return '';
                }

                if (state.loadingRequirements) {
                    return '<div class="requirement-list"><div class="requirement-list-head">Loading…</div></div>';
                }

                if (state.requirements.length === 0) {
                    return '<div class="requirement-list"><div class="requirement-list-head">No requirements match the filters.</div></div>';
                }

                const rows = state.requirements.map((requirement) => {
                    const selected = requirement.id === state.selectedRequirementId;
                    return `
                        <button type="button" class="requirement-row" data-requirement-id="${escapeHtml(requirement.id)}" aria-selected="${selected}">
                            <div class="requirement-row-meta">
                                <span>${escapeHtml(requirement.layer ?? '')}</span>
                                <span>${escapeHtml(requirement.type ?? '')}</span>
                                <span>${escapeHtml(requirement.priority ?? '')}</span>
                            </div>
                            <div class="requirement-row-text">${escapeHtml(requirement.text ?? requirement.id)}</div>
                        </button>
                    `;
                }).join('');

                return `
                    <div class="requirement-list">
                        <div class="requirement-list-head">${state.requirements.length} requirements</div>
                        <div class="requirement-list-scroll">${rows}</div>
                    </div>
                `;
            }

            function mainContent() {
                if (state.loadingProjects) {
                    return loadingPanel('Loading projects…');
                }

                if (state.projects.length === 0) {
                    return emptyPanel('No projects', 'Create a project to explore its requirements.');
                }

                if (!state.selectedProjectId) {
                    return emptyPanel('Select a project', 'Pick a project from the sidebar.');
                }

                if (state.loadingRequirements && state.requirements.length === 0) {
                    return loadingPanel('Loading requirements…');
                }

                if (state.requirements.length === 0) {
                    return emptyPanel('No requirements', 'This project has no requirements matching the current filters.');
                }

                if (!state.selectedRequirementId) {
                    return emptyPanel('Select a requirement', 'Pick a requirement from the sidebar to see its detail.');
                }

                const requirement = state.requirements.find((cap) => cap.id === state.selectedRequirementId);
                if (!requirement) {
                    return emptyPanel('Requirement not found', 'It may have been removed. Pick another from the sidebar.');
                }

                return `
                    ${header(requirement)}
                    <div class="detail">
                        ${acceptanceSection(requirement)}
                        ${relatedSection()}
                        ${findingsSection()}
                    </div>
                `;
            }

            function header(requirement) {
                const pills = [
                    requirement.layer && pill(requirement.layer),
                    requirement.type && pill(requirement.type),
                    requirement.priority && pill(requirement.priority, indicatorClass(requirement.priority === 'high' ? 'fail' : requirement.priority === 'medium' ? 'warn' : 'pass')),
                ].filter(Boolean).join('');

                return `
                    <header class="topbar">
                        <div class="title">
                            <h2>${escapeHtml(requirement.text ?? requirement.id)}</h2>
                            <div class="title-meta">
                                ${pills}
                                <span class="pill">${escapeHtml(requirement.id)}</span>
                            </div>
                        </div>
                    </header>
                `;
            }

            function pill(label, extraClass = '') {
                return `<span class="pill ${escapeHtml(extraClass)}">${escapeHtml(label)}</span>`;
            }

            function acceptanceSection(requirement) {
                const checks = requirement.acceptance_checks ?? [];
                const body = checks.length === 0
                    ? '<div class="empty"><span>No acceptance checks recorded.</span></div>'
                    : `<ol class="acceptance">${checks.map((check) => `<li>${escapeHtml(typeof check === 'string' ? check : check?.text ?? JSON.stringify(check))}</li>`).join('')}</ol>`;

                return `<section class="section"><h3>Acceptance checks</h3>${body}</section>`;
            }

            function relatedSection() {
                if (state.loadingDetail && !state.trace) {
                    return '<section class="section"><h3>Linked artifacts</h3><div class="loading">Loading trace…</div></section>';
                }

                if (state.trace?.error) {
                    return `<section class="section"><h3>Linked artifacts</h3><div class="error-panel">${escapeHtml(state.trace.error)}</div></section>`;
                }

                const nodes = (state.trace?.nodes ?? []).filter((node) => node.id !== state.selectedRequirementId);
                if (nodes.length === 0) {
                    return '<section class="section"><h3>Linked artifacts</h3><div class="empty"><span>No derived artifacts. Try widening direction or depth via <code>trace-query</code>.</span></div></section>';
                }

                const grouped = new Map();
                for (const node of nodes) {
                    if (!grouped.has(node.type)) {
                        grouped.set(node.type, []);
                    }
                    grouped.get(node.type).push(node);
                }

                const cards = [];
                const seenTypes = new Set();
                for (const { type, label } of RELATED_GROUPS) {
                    if (grouped.has(type)) {
                        cards.push(linkCard(label, grouped.get(type)));
                        seenTypes.add(type);
                    }
                }
                for (const [type, items] of grouped) {
                    if (!seenTypes.has(type)) {
                        cards.push(linkCard(title(type), items));
                    }
                }

                return `<section class="section"><h3>Linked artifacts</h3><div class="links-grid">${cards.join('')}</div></section>`;
            }

            function linkCard(heading, items) {
                const list = items.map((node) => `
                    <li>
                        <div class="link-label">${escapeHtml(node.label ?? node.id)}</div>
                        <div class="link-id">${escapeHtml(node.id)}</div>
                    </li>
                `).join('');

                return `<div class="links-card"><h4>${escapeHtml(heading)} · ${items.length}</h4><ul>${list}</ul></div>`;
            }

            function findingsSection() {
                const findings = state.findings.filter((finding) => finding.subject_id === state.selectedRequirementId);

                if (findings.length === 0) {
                    return '';
                }

                const rows = findings.map((finding) => `
                    <div class="row">
                        <span class="finding-rule">${escapeHtml(finding.rule ?? 'unknown.rule')}</span>
                        <span>${escapeHtml(finding.message ?? '')}</span>
                        <span class="pill ${indicatorClass(finding.severity ?? 'pending')} finding-pill">${escapeHtml(finding.severity ?? '')}</span>
                    </div>
                `).join('');

                return `<section class="section findings"><h3>Lint findings</h3><div class="rows">${rows}</div></section>`;
            }

            function loadingPanel(message) {
                return `<div class="loading">${escapeHtml(message)}</div>`;
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

    <div id="requirement-explorer"></div>
</x-mcp::app>
