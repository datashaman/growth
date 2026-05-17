<?php

namespace App\Support;

use App\Mcp\Servers\ArchitectureServer;
use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\ManagementServer;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Servers\VerificationServer;
use Illuminate\Support\Facades\File;

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
     * work. Authored as Markdown under `resources/prompts/roles/` (one file per
     * case value) so the persona is versioned in the repo, edits as plain
     * Markdown, and arrives over the wire with no client-side install.
     */
    public function personaInstructions(): string
    {
        return trim(File::get(resource_path("prompts/roles/{$this->value}.md")));
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
