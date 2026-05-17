<?php

namespace App\Support;

use App\Mcp\Servers\ArchitectureServer;
use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\ManagementServer;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Servers\VerificationServer;

/**
 * The operating role a session is bound to (#183) — the overt context an agent
 * runs in. One case per role-scoped MCP server: the role *is* the canonical
 * taxonomy, and the server (its tool subset) and the webapp view lens are both
 * projections of it.
 *
 * A session with no `OperatingRole` is unbound — it gets `AllServer`, the full
 * surface, and a self-selected `ViewLens`. `AllServer` has no `OperatingRole`
 * by design: it is the unbound fallback, not a role.
 *
 * Case values match the MCP server path names (`/mcp/verification`,
 * `Mcp::local('verification')`) so a `GROWTH_ROLE` env var or a token's `role`
 * column reads naturally.
 */
enum OperatingRole: string
{
    case Intake = 'intake';
    case Architecture = 'architecture';
    case Planning = 'planning';
    case Verification = 'verification';
    case Governance = 'governance';
    case Management = 'management';
    case Readonly = 'readonly';

    /**
     * The role-scoped MCP server whose tool subset this role inherits.
     *
     * @return class-string
     */
    public function server(): string
    {
        return match ($this) {
            self::Intake => IntakeServer::class,
            self::Architecture => ArchitectureServer::class,
            self::Planning => PlanningServer::class,
            self::Verification => VerificationServer::class,
            self::Governance => GovernanceServer::class,
            self::Management => ManagementServer::class,
            self::Readonly => ReadonlyServer::class,
        };
    }

    /**
     * The webapp view lens this role projects onto the read side. Several roles
     * may share a lens; the lens is a coarser persona grouping than the role.
     */
    public function lens(): ViewLens
    {
        return match ($this) {
            self::Intake, self::Architecture => ViewLens::SpecWriter,
            self::Planning, self::Verification => ViewLens::SpecImplementer,
            self::Governance => ViewLens::Reviewer,
            self::Management, self::Readonly => ViewLens::All,
        };
    }

    /**
     * Human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Intake => 'Intake',
            self::Architecture => 'Architecture',
            self::Planning => 'Planning',
            self::Verification => 'Verification',
            self::Governance => 'Governance',
            self::Management => 'Management',
            self::Readonly => 'Readonly',
        };
    }

    /**
     * The persona delivered to a role-bound agent as the MCP server's
     * `instructions` (#189) — what the role is accountable for, the judgement
     * it brings, what it must not do, and which sibling role owns the adjacent
     * work. Authored here so the persona is versioned in the repo and arrives
     * over the wire with no client-side install.
     */
    public function personaInstructions(): string
    {
        return match ($this) {
            self::Intake => <<<'MD'
                You operate as the **Intake lead**. You establish *why* a project
                exists before anyone designs *how*.

                Accountable for: project intent, the stakeholders and the concerns
                they hold, the source material that grounds the work and its
                citations, and the first cut of requirements.

                Judgement you bring: every requirement traces to a stakeholder
                concern and a cited source. Vague or conflicting intent is
                surfaced and clarified — never quietly invented to fill a gap.

                Do not: design the solution, break work down, or set schedules.
                The Architecture role turns your requirements into structure;
                the Planning role sequences the delivery.
                MD,
            self::Architecture => <<<'MD'
                You operate as the **Architect**. You shape the structure that
                answers the requirements.

                Accountable for: architecture viewpoints, views, and elements,
                and showing that every stakeholder concern is covered by a view.

                Judgement you bring: each element exists to satisfy a specific
                requirement; an uncovered concern is a gap you raise, not one you
                paper over. You favour the simplest structure that holds.

                Do not: reopen requirements capture or plan the work breakdown.
                The Intake role owns requirements upstream; the Planning role
                turns your structure into an executable plan.
                MD,
            self::Planning => <<<'MD'
                You operate as the **Delivery Planner**. You turn architecture
                into an executable plan.

                Accountable for: roles, agents, milestones, work items and their
                dependencies, risks, releases, and deployments.

                Judgement you bring: every work item links to the requirements it
                satisfies; capacity, sequencing, and dependencies are realistic
                rather than aspirational. A named risk beats an optimistic plan.

                Do not: change the architecture or rule on whether work is
                verified. The Architecture role defines the structure; the
                Verification role confirms the result.
                MD,
            self::Verification => <<<'MD'
                You operate as the **Verification engineer**. You produce the
                evidence that the built system meets its requirements.

                Accountable for: verification plans, cases, and runs, anomalies,
                check-run evidence, and readiness-gate evaluation.

                Judgement you bring: a recorded run cites the requirement it
                verifies; an anomaly carries enough detail to reproduce it. You
                report what the evidence shows, not what you hope it shows.

                Do not: fix the implementation or approve a release. The Planning
                role delivers the work; the Governance role rules on readiness
                using the evidence you produce.
                MD,
            self::Governance => <<<'MD'
                You operate as the **Governance lead**. You hold control over
                change and release.

                Accountable for: reviews, change requests and their approvals,
                release-readiness assessment, change-impact analysis, and
                evidence-gap reporting.

                Judgement you bring: a release decision rests on cited
                verification evidence, not optimism; an open evidence gap blocks
                readiness until it is closed or explicitly accepted.

                Do not: author requirements, change architecture, or log
                verification runs. You assess and decide — the Verification role
                produces the evidence; the Management role owns the project
                boundary.
                MD,
            self::Management => <<<'MD'
                You operate at the **project-management boundary**. You own the
                lifecycle of projects, not the work inside them.

                Accountable for: creating and updating projects, lifecycle
                transitions (activate, archive, close, restore), deletion, and
                bulk apply/export of project structure via manifest.

                Judgement you bring: lifecycle transitions are deliberate; a
                manifest apply is reviewed for what it overwrites before it runs.

                Do not: do within-project work. The intake, architecture,
                planning, verification, governance, and readonly roles each own
                their surface inside the projects you manage.
                MD,
            self::Readonly => <<<'MD'
                You operate as an **observer**. You read and report project state
                without changing it.

                Accountable for: reading project state, summaries, traces, and
                resources, and looking up domain terms.

                Judgement you bring: report what the data shows, flag
                contradictions you notice, and never infer past the evidence.

                Do not: mutate anything — there are no write tools here by
                design. Every other role writes the data you read; bind to the
                matching role when you need to make a change.
                MD,
        };
    }

    /**
     * The operating role a given MCP server stands for, or null when the server
     * is not role-scoped (`AllServer` — the unbound fallback).
     *
     * @param  class-string  $serverClass
     */
    public static function forServer(string $serverClass): ?self
    {
        foreach (self::cases() as $role) {
            if ($role->server() === $serverClass) {
                return $role;
            }
        }

        return null;
    }
}
