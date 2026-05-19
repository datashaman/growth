<?php

namespace App\Mcp\Servers\Concerns;

use App\Support\SurfaceContext;
use Laravel\Mcp\Server;

trait SurfaceServerDefaults
{
    use AuthenticatesLocalMcpSessions;

    protected function boot(): void
    {
        // High enough that one page holds a surface server's whole tool surface
        // — the AllServer union is the largest and must fit in a single list.
        $this->maxPaginationLength = 300;
        $this->defaultPaginationLength = 300;

        // Advertise the MCP logging capability so long-running tools may stream
        // structured `notifications/message` records, with the client choosing
        // the verbosity via `logging/setLevel`.
        $this->addCapability(Server::CAPABILITY_LOGGING);

        // Advertise the MCP completions capability so clients may autocomplete
        // prompt argument values via `completion/complete`.
        $this->addCapability(Server::CAPABILITY_COMPLETIONS);

        $this->bootTrustedLocalSession();

        app(SurfaceContext::class)->assertServerMatches(static::class);
    }
}
