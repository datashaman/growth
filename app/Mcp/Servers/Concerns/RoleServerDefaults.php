<?php

namespace App\Mcp\Servers\Concerns;

use App\Support\RoleContext;

trait RoleServerDefaults
{
    use AuthenticatesLocalMcpSessions;

    protected function boot(): void
    {
        $this->maxPaginationLength = 200;
        $this->defaultPaginationLength = 200;

        $this->bootTrustedLocalSession();

        app(RoleContext::class)->assertServerMatches(static::class);
    }
}
