<x-mcp::app :title="$title">
    <x-slot:head>
        @include('mcp.partials._app-shell')
        <script src="https://unpkg.com/vis-network@9/standalone/umd/vis-network.min.js"></script>
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
                overflow-y: auto;
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

            .capability-list {
                border: 1px solid var(--line);
                border-radius: 6px;
                background: var(--panel);
                display: flex;
                flex-direction: column;
                max-height: 320px;
                overflow: hidden;
            }

            .capability-list-head {
                border-bottom: 1px solid var(--line-soft);
                color: var(--muted);
                font-size: 12px;
                padding: 8px 11px;
            }

            .capability-list-scroll {
                overflow-y: auto;
            }

            .capability-row {
                background: transparent;
                border: 0;
                border-top: 1px solid var(--line-soft);
                color: var(--text);
                cursor: pointer;
                display: grid;
                font-size: 12px;
                gap: 2px;
                padding: 8px 11px;
                text-align: left;
                width: 100%;
            }

            .capability-row:first-of-type {
                border-top: 0;
            }

            .capability-row:hover {
                background: var(--panel-soft);
            }

            .capability-row[aria-selected="true"] {
                background: var(--accent-soft);
            }

            .capability-row-meta {
                color: var(--muted);
                font-size: 10px;
                letter-spacing: .04em;
                text-transform: uppercase;
            }

            .main {
                display: flex;
                flex-direction: column;
                min-width: 0;
                padding: 22px 28px 28px;
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

            .controls {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 14px;
            }

            .controls .field {
                min-width: 130px;
            }

            .legend {
                align-items: center;
                color: var(--muted);
                display: flex;
                flex-wrap: wrap;
                font-size: 12px;
                gap: 12px;
                margin-top: 14px;
            }

            .legend-item {
                align-items: center;
                display: flex;
                gap: 6px;
            }

            .legend-dot {
                border-radius: 50%;
                display: inline-block;
                height: 10px;
                width: 10px;
            }

            .graph-frame {
                background: var(--panel);
                border: 1px solid var(--line);
                border-radius: 8px;
                flex: 1;
                margin-top: 14px;
                min-height: 360px;
                overflow: hidden;
                position: relative;
            }

            .graph-canvas {
                height: 100%;
                width: 100%;
            }

            .graph-overlay {
                align-items: center;
                background: var(--panel);
                bottom: 0;
                color: var(--muted);
                display: flex;
                justify-content: center;
                left: 0;
                padding: 24px;
                position: absolute;
                right: 0;
                text-align: center;
                top: 0;
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
            const { escapeHtml, title } = window.GrowthApp;

            const TYPE_COLORS = {
                requirement:        '#147d64',
                concern:            '#a64ad8',
                design_view:        '#1f6feb',
                design_element:     '#0d8def',
                test_plan:          '#b06b00',
                test_case:          '#d28e00',
                test_run:           '#f0a500',
                anomaly:            '#b42318',
                source:             '#6e7681',
                citation:           '#8a929b',
                work_item:          '#9333ea',
                milestone:          '#5b21b6',
                risk:               '#ef4444',
                review:             '#0ea5e9',
                review_finding:     '#0284c7',
                review_participant: '#075985',
                review_target:      '#0c4a6e',
                stakeholder:        '#2dd3a4',
                project_plan:       '#7e22ce',
                change_request:     '#dc2626',
                change_impact:      '#ea580c',
                artifact_relation:  '#9a3412',
                user:               '#475569',
                agent:              '#334155',
            };

            const FALLBACK_COLOR = '#94a0a8';

            const state = {
                app,
                projects: [],
                selectedProjectId: null,
                capabilities: [],
                startingId: '',
                idInput: '',
                depth: 3,
                direction: 'both',
                trace: null,
                loadingProjects: true,
                loadingCapabilities: false,
                loadingTrace: false,
                error: null,
            };

            let network = null;
            const root = document.getElementById('trace-graph');

            app.autoResize();

            app.onToolInput((params) => {
                const projectId = params?.arguments?.project_id ?? params?.project_id ?? null;
                if (projectId && projectId !== state.selectedProjectId) {
                    state.selectedProjectId = projectId;
                    state.startingId = '';
                    state.idInput = '';
                    state.trace = null;
                    loadCapabilities();
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
                        state.startingId = '';
                        state.idInput = '';
                        state.trace = null;
                        loadCapabilities();
                    }
                } catch {
                    // ignore non-JSON
                }
            });

            root.addEventListener('change', (event) => {
                const target = event.target;
                if (target.id === 'project-picker') {
                    state.selectedProjectId = target.value || null;
                    state.startingId = '';
                    state.idInput = '';
                    state.trace = null;
                    loadCapabilities();
                    return;
                }
                if (target.id === 'depth-picker') {
                    state.depth = Number(target.value);
                    if (state.startingId) {
                        loadTrace();
                    }
                    return;
                }
                if (target.id === 'direction-picker') {
                    state.direction = target.value;
                    if (state.startingId) {
                        loadTrace();
                    }
                }
            });

            root.addEventListener('input', (event) => {
                if (event.target.id === 'starting-id-input') {
                    state.idInput = event.target.value.trim();
                }
            });

            root.addEventListener('keydown', (event) => {
                if (event.target.id === 'starting-id-input' && event.key === 'Enter') {
                    event.preventDefault();
                    if (state.idInput && state.idInput !== state.startingId) {
                        state.startingId = state.idInput;
                        loadTrace();
                    }
                }
            });

            root.addEventListener('click', (event) => {
                if (event.target.closest('[data-expand]')) {
                    app.requestDisplayMode('fullscreen');
                    return;
                }
                if (event.target.closest('[data-trace-go]')) {
                    if (state.idInput && state.idInput !== state.startingId) {
                        state.startingId = state.idInput;
                        loadTrace();
                    }
                    return;
                }
                const row = event.target.closest('[data-capability-id]');
                if (row) {
                    const id = row.dataset.capabilityId;
                    state.startingId = id;
                    state.idInput = id;
                    loadTrace();
                }
            });

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
                state.projects = payload?.projects ?? [];
                state.loadingProjects = false;

                if (!state.selectedProjectId && state.projects.length > 0) {
                    state.selectedProjectId = state.projects[0].id;
                }

                if (state.selectedProjectId) {
                    await loadCapabilities();
                } else {
                    render();
                }
            }

            async function loadCapabilities() {
                if (!state.selectedProjectId) {
                    state.capabilities = [];
                    render();
                    return;
                }

                state.loadingCapabilities = true;
                render();

                const result = await app.callServerTool({
                    name: 'list-capabilities',
                    arguments: { project_id: state.selectedProjectId, limit: 200 },
                });

                state.loadingCapabilities = false;

                if (result.isError) {
                    state.error = result.content?.[0]?.text ?? 'Unable to load capabilities.';
                    state.capabilities = [];
                    render();
                    return;
                }

                const payload = window.GrowthApp.parseToolPayload(result);
                state.capabilities = payload?.results ?? [];

                if (!state.startingId && state.capabilities.length > 0) {
                    state.startingId = state.capabilities[0].id;
                    state.idInput = state.startingId;
                    await loadTrace();
                    return;
                }

                render();
            }

            async function loadTrace() {
                if (!state.startingId) {
                    state.trace = null;
                    render();
                    return;
                }

                state.loadingTrace = true;
                state.error = null;
                render();

                const result = await app.callServerTool({
                    name: 'trace-query',
                    arguments: {
                        id: state.startingId,
                        depth: state.depth,
                        direction: state.direction,
                    },
                });

                state.loadingTrace = false;

                if (result.isError) {
                    state.error = result.content?.[0]?.text ?? 'Unable to walk trace.';
                    state.trace = null;
                    render();
                    return;
                }

                state.trace = window.GrowthApp.parseToolPayload(result) ?? { nodes: [], edges: [] };
                render();
                drawGraph();
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
                            <h1>Trace Graph</h1>
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
                    <label class="field">
                        <span>Starting artifact ID</span>
                        <input id="starting-id-input" class="input" type="text" placeholder="01HXXXX… (any artifact)" value="${escapeHtml(state.idInput ?? '')}">
                    </label>
                    <button type="button" class="button" data-trace-go>Trace</button>
                    ${capabilityList()}
                `;
            }

            function capabilityList() {
                if (!state.selectedProjectId) {
                    return '';
                }

                if (state.loadingCapabilities) {
                    return '<div class="capability-list"><div class="capability-list-head">Loading capabilities…</div></div>';
                }

                if (state.capabilities.length === 0) {
                    return '<div class="capability-list"><div class="capability-list-head">No capabilities — paste any artifact ID above.</div></div>';
                }

                const rows = state.capabilities.map((capability) => {
                    const selected = capability.id === state.startingId;
                    return `
                        <button type="button" class="capability-row" data-capability-id="${escapeHtml(capability.id)}" aria-selected="${selected}">
                            <span class="capability-row-meta">${escapeHtml(capability.layer ?? '')} · ${escapeHtml(capability.type ?? '')}</span>
                            <span>${escapeHtml(capability.text ?? capability.id)}</span>
                        </button>
                    `;
                }).join('');

                return `
                    <div class="capability-list">
                        <div class="capability-list-head">Capabilities · ${state.capabilities.length}</div>
                        <div class="capability-list-scroll">${rows}</div>
                    </div>
                `;
            }

            function mainContent() {
                if (state.loadingProjects) {
                    return loadingPanel('Loading projects…');
                }

                if (state.projects.length === 0) {
                    return emptyPanel('No projects', 'Create a project to walk its trace graph.');
                }

                if (!state.selectedProjectId) {
                    return emptyPanel('Select a project', 'Pick a project from the sidebar.');
                }

                if (!state.startingId) {
                    return emptyPanel('Select a starting artifact', 'Pick a capability from the sidebar or paste an artifact ID.');
                }

                return `
                    ${header()}
                    ${controls()}
                    ${legend()}
                    <div class="graph-frame">
                        <div id="graph-canvas" class="graph-canvas"></div>
                        ${graphOverlay()}
                    </div>
                `;
            }

            function header() {
                const startingNode = (state.trace?.nodes ?? []).find((node) => node.id === state.startingId);
                const label = startingNode?.label ?? state.startingId;
                const type = startingNode?.type ?? 'unknown';
                const nodeCount = state.trace?.nodes?.length ?? 0;
                const edgeCount = state.trace?.edges?.length ?? 0;

                return `
                    <header class="topbar">
                        <div class="title">
                            <h2>${escapeHtml(label)}</h2>
                            <div class="title-meta">
                                <span class="pill">${escapeHtml(type)}</span>
                                <span class="pill">${escapeHtml(state.startingId)}</span>
                                <span>${nodeCount} nodes · ${edgeCount} edges</span>
                            </div>
                        </div>
                    </header>
                `;
            }

            function controls() {
                const depthOptions = [1, 2, 3, 4, 5, 6].map((value) => `
                    <option value="${value}" ${value === state.depth ? 'selected' : ''}>${value}</option>
                `).join('');

                const directionOptions = ['both', 'down', 'up'].map((value) => `
                    <option value="${escapeHtml(value)}" ${value === state.direction ? 'selected' : ''}>${title(value)}</option>
                `).join('');

                return `
                    <div class="controls">
                        <label class="field">
                            <span>Depth</span>
                            <select id="depth-picker" class="select">${depthOptions}</select>
                        </label>
                        <label class="field">
                            <span>Direction</span>
                            <select id="direction-picker" class="select">${directionOptions}</select>
                        </label>
                    </div>
                `;
            }

            function legend() {
                const present = new Set((state.trace?.nodes ?? []).map((node) => node.type));
                if (present.size === 0) {
                    return '';
                }

                const items = [...present].sort().map((type) => `
                    <span class="legend-item">
                        <span class="legend-dot" style="background:${colorFor(type)}"></span>
                        <span>${escapeHtml(title(type))}</span>
                    </span>
                `).join('');

                return `<div class="legend">${items}</div>`;
            }

            function graphOverlay() {
                if (state.loadingTrace) {
                    return '<div class="graph-overlay">Walking trace…</div>';
                }
                if (!state.trace) {
                    return '<div class="graph-overlay">Trace will appear here.</div>';
                }
                if ((state.trace.nodes ?? []).length === 0) {
                    return '<div class="graph-overlay">No artifact found for that ID.</div>';
                }
                if ((state.trace.nodes ?? []).length === 1) {
                    return '<div class="graph-overlay">Only the starting artifact — nothing else reachable at this depth/direction.</div>';
                }
                return '';
            }

            function colorFor(type) {
                return TYPE_COLORS[type] ?? FALLBACK_COLOR;
            }

            function drawGraph() {
                if (!state.trace || (state.trace.nodes ?? []).length === 0) {
                    return;
                }

                if (typeof vis === 'undefined' || !vis.Network) {
                    return;
                }

                const container = document.getElementById('graph-canvas');
                if (!container) {
                    return;
                }

                const nodes = state.trace.nodes.map((node) => {
                    const isStart = node.id === state.startingId;
                    return {
                        id: node.id,
                        label: trimLabel(node.label ?? node.id),
                        title: `${node.type}: ${node.label ?? ''}\n${node.id}`,
                        color: {
                            background: colorFor(node.type),
                            border: isStart ? '#ffffff' : colorFor(node.type),
                            highlight: { background: colorFor(node.type), border: '#ffffff' },
                        },
                        borderWidth: isStart ? 3 : 1,
                        shape: isStart ? 'star' : 'dot',
                        size: isStart ? 22 : 14,
                        font: { color: '#f0f3f5', size: 12, strokeWidth: 3, strokeColor: '#0f1419' },
                    };
                });

                const edges = (state.trace.edges ?? []).map((edge) => ({
                    from: edge.from,
                    to: edge.to,
                    label: edge.label,
                    arrows: edge.direction === 'up' ? 'from' : 'to',
                    color: { color: '#94a0a8', highlight: '#2dd3a4' },
                    font: { color: '#94a0a8', size: 10, strokeWidth: 0, align: 'middle' },
                    smooth: { type: 'dynamic' },
                }));

                if (network) {
                    network.destroy();
                    network = null;
                }

                network = new vis.Network(container, { nodes, edges }, {
                    physics: {
                        stabilization: { iterations: 200, fit: true },
                        barnesHut: { gravitationalConstant: -8000, springLength: 140 },
                    },
                    interaction: { hover: true, tooltipDelay: 200 },
                    nodes: { shape: 'dot' },
                });
            }

            function trimLabel(value) {
                const text = String(value ?? '');
                return text.length > 36 ? text.slice(0, 33) + '…' : text;
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

    <div id="trace-graph"></div>
</x-mcp::app>
