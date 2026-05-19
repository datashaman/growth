<?php

namespace App\Mcp\Servers\Concerns;

use App\Support\OperatingRole;
use App\Support\RoleContext;

trait RoleServerDefaults
{
    use AuthenticatesLocalMcpSessions;

    protected function boot(): void
    {
        // High enough that one page holds a role server's whole tool surface
        // — the AllServer union is the largest and must fit in a single list.
        $this->maxPaginationLength = 300;
        $this->defaultPaginationLength = 300;

        $this->bootTrustedLocalSession();

        app(RoleContext::class)->assertServerMatches(static::class);

        $role = OperatingRole::forServer(static::class);
        if ($role !== null) {
            $this->instructions = $role->personaInstructions();
        }
    }
}
