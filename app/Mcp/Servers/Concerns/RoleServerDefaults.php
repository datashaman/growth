<?php

namespace App\Mcp\Servers\Concerns;

trait RoleServerDefaults
{
    use AuthenticatesLocalMcpSessions;

    public int $maxPaginationLength = 200;

    public int $defaultPaginationLength = 200;

    protected function boot(): void
    {
        $this->bootTrustedLocalSession();
    }
}
