<?php

namespace App\Mcp\Servers\Concerns;

trait RoleServerDefaults
{
    use AuthenticatesLocalMcpSessions;

    protected function boot(): void
    {
        $this->maxPaginationLength = 200;
        $this->defaultPaginationLength = 200;

        $this->bootTrustedLocalSession();
    }
}
