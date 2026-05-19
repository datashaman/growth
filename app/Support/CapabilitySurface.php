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
 * The capability surface a session is bound to (#183) — a structural grouping
 * of MCP tools, the overt context an agent runs in. One case per role-scoped
 * MCP server: the surface *is* the canonical taxonomy, and the server (its tool
 * subset) and the webapp view lens are both projections of it.
 *
 * A session with no `CapabilitySurface` is unbound — it gets `AllServer`, the
 * full surface, and a self-selected `ViewLens`. `AllServer` has no
 * `CapabilitySurface` by design: it is the unbound fallback, not a surface.
 *
 * Case values match the MCP server path names (`/mcp/verification`,
 * `Mcp::local('verification')`) so a `GROWTH_SURFACE` env var or a token's
 * `surface` column reads naturally.
 */
enum CapabilitySurface: string
{
    case Intake = 'intake';
    case Architecture = 'architecture';
    case Planning = 'planning';
    case Verification = 'verification';
    case Governance = 'governance';
    case Management = 'management';
    case Readonly = 'readonly';

    /**
     * The role-scoped MCP server whose tool subset this surface inherits.
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
     * The webapp view lens this surface projects onto the read side. Several
     * surfaces may share a lens; the lens is a coarser persona grouping than
     * the surface.
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
     * Human-readable label for the surface.
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
     * The persona delivered to a surface-bound agent as the MCP server's
     * `instructions` (#189) — what the surface is accountable for, the
     * judgement it brings, what it must not do, and which sibling surface owns
     * the adjacent work. Authored as Markdown under `resources/prompts/roles/`
     * (one file per case value) so the persona is versioned in the repo, edits
     * as plain Markdown, and arrives over the wire with no client-side install.
     */
    public function personaInstructions(): string
    {
        return trim(File::get(resource_path("prompts/roles/{$this->value}.md")));
    }

    /**
     * The capability surface a given MCP server stands for, or null when the
     * server is not role-scoped (`AllServer` — the unbound fallback).
     *
     * @param  class-string  $serverClass
     */
    public static function forServer(string $serverClass): ?self
    {
        foreach (self::cases() as $surface) {
            if ($surface->server() === $serverClass) {
                return $surface;
            }
        }

        return null;
    }
}
