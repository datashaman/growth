<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\ArchitectureResource;
use App\Mcp\Resources\CapabilitiesResource;
use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\RigorLevelsResource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\BulkLink;
use App\Mcp\Tools\DeleteArchitectureElement;
use App\Mcp\Tools\DeleteArchitectureView;
use App\Mcp\Tools\DeleteArchitectureViewpoint;
use App\Mcp\Tools\LintArchitecture;
use App\Mcp\Tools\ListArchitectureElements;
use App\Mcp\Tools\ListArchitectureViewpoints;
use App\Mcp\Tools\ListArchitectureViews;
use App\Mcp\Tools\ListCapabilities;
use App\Mcp\Tools\Projects\ListProjects;
use App\Mcp\Tools\Trace\TraceQuery;
use App\Mcp\Tools\UpsertArchitectureElements;
use App\Mcp\Tools\UpsertArchitectureView;
use App\Mcp\Tools\UpsertArchitectureViewpoint;
use App\Mcp\Tools\WhoAmI;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Architecture Server')]
#[Version('0.1.0')]
#[Instructions('Shape architecture viewpoints, views, elements, and concern coverage.')]
class ArchitectureServer extends Server
{
    use RoleServerDefaults;

    protected array $tools = [
        WhoAmI::class,
        ListProjects::class,
        ListCapabilities::class,
        LintArchitecture::class,
        UpsertArchitectureViewpoint::class,
        ListArchitectureViewpoints::class,
        DeleteArchitectureViewpoint::class,
        UpsertArchitectureView::class,
        ListArchitectureViews::class,
        DeleteArchitectureView::class,
        UpsertArchitectureElements::class,
        ListArchitectureElements::class,
        DeleteArchitectureElement::class,
        BulkLink::class,
        TraceQuery::class,
    ];

    protected array $resources = [
        PlaybookResource::class,
        RigorLevelsResource::class,
        ProjectIndexResource::class,
        CapabilitiesResource::class,
        ArchitectureResource::class,
    ];
}
