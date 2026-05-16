<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\RigorLevelsResource;
use App\Mcp\Resources\StarterTemplate1Resource;
use App\Mcp\Resources\StarterTemplate2Resource;
use App\Mcp\Resources\StarterTemplate3Resource;
use App\Mcp\Resources\StarterTemplate4Resource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Manifest\ApplyManifest;
use App\Mcp\Tools\Manifest\ExportManifest;
use App\Mcp\Tools\Projects\CreateProject;
use App\Mcp\Tools\Projects\DeleteProject;
use App\Mcp\Tools\Projects\ListProjects;
use App\Mcp\Tools\Projects\ResolveProjectByRepo;
use App\Mcp\Tools\Projects\UpdateProject;
use App\Mcp\Tools\Projects\UpsertProject;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Management Server')]
#[Version('0.1.0')]
#[Instructions('Manage project lifecycle: create, update, archive/restore via status, delete, and bulk apply/export of project structure via manifest. Operates at the project boundary; within-project work belongs on the intake/architecture/planning/verification/governance/readonly servers.')]
class ManagementServer extends Server
{
    use RoleServerDefaults;

    protected array $tools = [
        WhoAmI::class,
        ListProjects::class,
        ResolveProjectByRepo::class,
        CreateProject::class,
        UpdateProject::class,
        UpsertProject::class,
        DeleteProject::class,
        ApplyManifest::class,
        ExportManifest::class,
    ];

    protected array $resources = [
        PlaybookResource::class,
        RigorLevelsResource::class,
        ProjectIndexResource::class,
        StarterTemplate1Resource::class,
        StarterTemplate2Resource::class,
        StarterTemplate3Resource::class,
        StarterTemplate4Resource::class,
    ];
}
