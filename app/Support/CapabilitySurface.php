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
 * The capability surface a session is bound to (#183) — a structural grouping
 * of MCP tools, the overt context an agent runs in. One case per role-scoped
 * MCP server.
 *
 * A session with no `CapabilitySurface` is unbound — it gets `AllServer`, the
 * full surface. `AllServer` has no `CapabilitySurface` by design: it is the
 * unbound fallback, not a surface.
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
