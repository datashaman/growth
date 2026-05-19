<?php

namespace App\Mcp\Servers\Concerns;

use App\Support\CapabilitySurface;
use App\Support\SurfaceContext;

trait SurfaceServerDefaults
{
    use AuthenticatesLocalMcpSessions;

    protected function boot(): void
    {
        // High enough that one page holds a surface server's whole tool surface
        // — the AllServer union is the largest and must fit in a single list.
        $this->maxPaginationLength = 300;
        $this->defaultPaginationLength = 300;

        $this->bootTrustedLocalSession();

        app(SurfaceContext::class)->assertServerMatches(static::class);

        $surface = CapabilitySurface::forServer(static::class);
        if ($surface !== null) {
            $this->instructions = $surface->personaInstructions();
        }
    }
}
