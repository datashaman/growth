<?php

namespace App\Mcp\Servers;

use App\Mcp\Servers\Concerns\SurfaceServerDefaults;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use ReflectionClass;

#[Name('All Server')]
#[Version('0.1.0')]
#[Instructions('Expose the complete MCP surface for power users and integration checks.')]
class AllServer extends Server
{
    use SurfaceServerDefaults {
        boot as bootSurfaceServerDefaults;
    }

    private const ROLE_SERVERS = [
        ManagementServer::class,
        IntakeServer::class,
        ArchitectureServer::class,
        PlanningServer::class,
        VerificationServer::class,
        GovernanceServer::class,
        ReadonlyServer::class,
    ];

    protected function boot(): void
    {
        $this->tools = $this->unionFromRoleServers('tools');
        $this->resources = $this->unionFromRoleServers('resources');
        $this->prompts = $this->unionFromRoleServers('prompts');

        $this->bootSurfaceServerDefaults();
    }

    /**
     * Deduplicated union of one primitive array across all role servers.
     *
     * @return array<int, class-string>
     */
    private function unionFromRoleServers(string $property): array
    {
        $seen = [];
        foreach (self::ROLE_SERVERS as $server) {
            $values = (new ReflectionClass($server))->getDefaultProperties()[$property] ?? [];
            foreach ($values as $class) {
                $seen[$class] = true;
            }
        }

        return array_keys($seen);
    }
}
