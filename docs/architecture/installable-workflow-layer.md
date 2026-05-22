# Growth as an installable workflow layer

Growth should be installable into an existing project as the durable workflow
store and context layer for AI-assisted work. The local repository keeps code,
tests, implementation notes, and optional projections; Growth keeps the project
state that should survive across chats: intent, requirements, architecture,
roles, personas, decisions, plans, evidence, reviews, and readiness posture.

The Client Agent still decides and acts. Growth serves context, exposes MCP
tools, records attribution, and reports drift. It does not host the agent or
pretend to prove that a chat answer was good.

## Product boundary

Growth owns durable workflow reality:

- project intent, stakeholders, concerns, and sources;
- requirements, acceptance checks, architecture views, and design elements;
- project roles, capabilities, personas, and responsibility assignments;
- plans, baselines, work items, milestones, reviews, changes, and decisions;
- delivery evidence, verification posture, readiness gates, and contradictions;
- the expected local adapter shape for a repository that is Growth-mediated.

The repository owns implementation reality:

- source code, tests, build commands, and deployment configuration;
- local agent instruction files and optional skills;
- generated or hand-maintained projections of Growth state;
- evidence files and references that support Growth records.

The boundary is important: local files can help a Client Agent orient itself,
but they should not become a second project-management store unless they are
explicitly classified as projections or evidence.

## Project Adapter

A Project Adapter is the local repo configuration that binds a repository to a
Growth Project. It is not a workflow store. It is a managed adapter layer that
tells Client Agents where the durable state lives and how to use it.

An adapter may include:

- managed sections in `AGENTS.md`, `CLAUDE.md`, or client-specific instruction
  files;
- MCP configuration hints and project binding identifiers;
- issue tracker mapping and triage label vocabulary;
- command, test, and build discovery;
- pointers to Growth MCP resources, artifact briefs, and workflow guidance;
- optional local skills that call Growth or read Growth-served resources.

Growth owns the expected adapter shape. Local files remain editable, but
Growth should distinguish managed sections from local sections so diagnostics
can report drift without overwriting project-specific instructions.

## Workflow Mode

Role answers "in what accountability are you acting?" Workflow Mode answers
"what kind of work are you doing right now?"

Workflow Mode is Session-scoped because multiple Client Agent sessions may work
on the same Project in different modes at the same time. It is a closed Growth
set, with optional project and role overlays for local practice.

Starter modes:

| Mode | Purpose |
| --- | --- |
| `capture_intent` | Gather stakeholders, concerns, sources, and project framing. |
| `shape_requirements` | Turn intent into requirements, acceptance checks, and traceable scope. |
| `record_decision` | Capture a decision, alternatives, rationale, and affected artifacts. |
| `prepare_handoff` | Bundle requirements, architecture, plan, risks, and evidence for implementation. |
| `review_implementation` | Compare delivered work against requirements, decisions, and verification evidence. |
| `diagnose_drift` | Find divergence between Growth state, local projections, issues, docs, and code. |

Canonical guidance belongs on Workflow Mode first, then is refined by Role
persona, Role capabilities, Project facts, and Project Adapter facts.

## On-form chat

Growth should not claim that a Client Agent obeyed instructions perfectly.
"On-form" means the Session has the right operating context loaded and recent
observable actions are compatible with that context.

Observable checks may include:

- the Session is bound to a Workspace and Project;
- the Session has adopted a Role when the Workflow Mode expects one;
- the Role persona and capabilities have been served;
- the Session has entered a Workflow Mode;
- the Project Adapter points to the same Growth Project;
- recent tool calls match the expected tool families for the Role and Mode;
- relevant Growth records were created or updated after chat decisions;
- local specs are classified as projections, evidence, or source candidates;
- diagnostics report drift between local files and Growth's expected adapter
  shape.

This is advisory context projection and drift reporting, not enforcement.

## Diagnostics before mutation

The first implementation slice should be readiness and conformance diagnostics,
not bootstrap mutation. Diagnostics answer whether a repository is ready for
Growth-mediated work and what gaps remain.

Checks should cover:

- MCP connection and server configuration;
- Workspace and Project binding;
- Project Adapter presence and managed-section drift;
- local instruction files pointing to Growth as the workflow store;
- issue tracker mapping;
- available Roles, Personas, Capabilities, and Workflow Modes;
- command and test discovery;
- classification of existing specs, docs, ADRs, issues, and planning files;
- clear paths for recording decisions, requirements, and handoffs in Growth.

The diagnostic should suggest fixes but avoid silently enforcing a process the
project has not chosen.

## Bootstrap flow

Bootstrap turns an existing repo into a Growth-aware repo. It should be
Growth-led and conservative:

1. Inspect or ask for project identity, repository root, and issue tracker
   conventions.
2. Bind the repo to a Growth Workspace and Project.
3. Classify existing artifacts before importing them.
4. Write or update managed adapter sections in local instruction/config files.
5. Record the expected adapter shape in Growth.
6. Run diagnostics again and report remaining gaps.

Artifact classification comes before import:

| Classification | Meaning | Default action |
| --- | --- | --- |
| Source candidate | Appears to contain durable workflow state that may belong in Growth. | Ask before importing. |
| Projection | Mirrors or exports Growth state. | Reconcile against Growth and report drift. |
| Evidence | Supports decisions, verification, or implementation but is not workflow state. | Reference from Growth records. |

Imports should be explicit because existing documents may be stale,
aspirational, or contradicted by project state.

## Skill pack and procedures

Growth may provide optional local skills or served workflow procedures for
common modes:

- capture intent;
- shape requirements;
- record architecture decisions;
- break work into issues or work items;
- prepare an implementation handoff;
- review implementation against requirements and decisions;
- diagnose drift between chat, code, requirements, and Growth state;
- update Growth when decisions happen in chat.

Skills must call Growth or read Growth-served resources. They must not maintain
independent durable workflow state in local skill files, specs, or markdown
issues unless those files are explicitly projections or evidence.

## Relationship to other workflow tools

Growth is adjacent to GSD, SpecKit, OpenSpec, and repo-native planning
workflows, but the store ownership is different.

| System shape | Store of record | Growth relationship |
| --- | --- | --- |
| Repo-native specs | Markdown/spec files in the repository | Treat as source candidates, projections, or evidence. |
| Issue-driven workflows | GitHub/Jira/etc. issues | Map issues to Growth work items, decisions, reviews, or evidence. |
| SpecKit/OpenSpec-style change specs | Local spec/change directories | Classify before import; avoid parallel durable stores by default. |
| Local agent skills | Client-side procedures | Keep as convenience wrappers that call Growth. |
| Growth | Database-backed MCP workflow layer | Own durable project state and serve context/resources to clients. |

Coexistence means Growth can read, reference, import, export, or reconcile
external artifacts without copying their whole workflow model.

## First follow-up slices

1. **Readiness diagnostic resource/tool.** Report Project Adapter presence,
   Growth binding, instruction drift, issue tracker mapping, command discovery,
   and artifact classification gaps.
2. **Project Adapter model.** Persist expected adapter shape, managed section
   hashes, repo path metadata, and client-specific instruction targets.
3. **Workflow Mode model.** Add closed-set modes, served guidance, and
   Session-scoped adoption.
4. **Bootstrap writer.** Generate or update managed sections only after the
   diagnostic and artifact classification model are in place.
5. **Skill pack projection.** Produce optional local skills/procedures that
   call Growth instead of storing workflow state locally.

## Open questions

- Which local instruction files should be first-class adapter targets across
  clients?
- Should adapter reconciliation be exposed as an MCP tool, a webapp flow, a
  local CLI command, or all three?
- How should managed sections be delimited so humans can edit local guidance
  safely?
- What persistence shape should hold expected adapter state and drift history?
- Which artifact classifiers should be heuristic-only, and which should ask a
  human before labeling?
- How should Workflow Mode overlays compose with Role persona and Project
  guidance when they conflict?
