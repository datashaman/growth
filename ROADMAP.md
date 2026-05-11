# Roadmap

This project is a software project specification and governance workbench. The
goal is to model enough of a software development project that teams can specify,
plan, review, trace, and assess readiness from one coherent project model.

## 1. Specify

Capture the product and engineering intent.

Current:

- requirements and SRS-style resources
- stakeholders and concerns
- sources and artifact citations
- design views, viewpoints, and design elements
- test plans, test cases, test runs, and anomalies
- traceability across requirements, design, tests, and sources
- acceptance criteria on requirements with SRS rendering and lint checks
- requirement review coverage reporting and high-integrity review linting
- reusable specification templates for common requirement sets

## 2. Plan

Turn the specification into an executable project plan.

Current:

- project management plans
- WBS-style work items
- milestones
- roles, agents, RACI assignments, and dependencies
- plan baselines
- project risks and risk assessment prompts
- numeric capacity, effort, and cost planning rollups
- schedule health and dependency risk summaries
- baseline comparison reports with per-field before/after values

## 3. Review

Record review decisions and create auditable evidence.

Current:

- public guidance for review readiness review/audit process vocabulary
- public NASA guidance source for formal inspection rule-pack extraction
- reusable review plans with procedures, criteria, expected roles, and checklists
- review records for management reviews, technical reviews, inspections, walkthroughs, and audits
- reviewed artifact targets
- review participant role assignments and signoff evidence
- review findings and dispositions
- project review resource and trace edges
- review linting for targets, participants, entry/exit criteria, decisions, signoff, and unresolved findings
- append-only review decision audit events

## 4. Execute

Connect the plan to real delivery activity.

Current:

- work items can be planned and traced to requirements
- work items can link to implementation evidence: commits, pull requests, and branches
- CI/check run evidence can be attached to delivery links and traced back to work items
- release and deployment records link shipped versions, environments, work items, and delivery evidence
- implementation status rollups combine work item status, delivery evidence, checks, and deployments

## 5. Control Change

Manage change without losing traceability.

Current:

- plan baselines provide a foundation for change tracking
- change requests with requester, review linkage, decision fields, and impacted artifact links
- project change register resource and trace edges
- change-control linting for impacts, review evidence, decisions, approval, and unresolved analysis
- baseline delta comparison for plan/WBS snapshots with approved-change coverage
- baseline drift linting for missing high-integrity baselines, uncovered changes, and removed artifacts
- change impact analysis expands impacted artifacts into trace context
- append-only change approval events record decision/status transitions
- explicit artifact relations record supersession and replacement links

## 6. Assure

Assess whether the project is coherent, ready, and defensible.

Current:

- linting for requirements, design, tests, PMP, and project readiness
- lifecycle readiness gates combining lint, change-control, and implementation evidence
- compliance evidence bundles index canonical resources, guidance, counts, and readiness gates
- risk-adjusted release readiness combines gates, high-exposure risks, checks, and deployment state
- cross-artifact contradiction checks catch conflicting work, anomaly, check, deployment, and change states
- evidence-gap reporting flags missing delivery evidence, missing decision audit events, and released work without deployment evidence
- traceability audit prompts
- risk posture checks
- public guidance and public guidance catalog
