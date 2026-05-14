<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\CaptureIntent;
use App\Mcp\Prompts\StartProject;
use App\Mcp\Resources\CapabilitiesResource;
use App\Mcp\Resources\IntentResource;
use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\RigorLevelsResource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\Concerns\DeleteConcern;
use App\Mcp\Tools\DeleteCapability;
use App\Mcp\Tools\DeleteCitation;
use App\Mcp\Tools\DeleteProject;
use App\Mcp\Tools\LintCapabilities;
use App\Mcp\Tools\ListCapabilities;
use App\Mcp\Tools\ListCitations;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\LookupTerm;
use App\Mcp\Tools\Sources\DeleteSource;
use App\Mcp\Tools\Sources\ListSources;
use App\Mcp\Tools\Stakeholders\DeleteStakeholder;
use App\Mcp\Tools\Trace\TraceQuery;
use App\Mcp\Tools\UpsertCapabilities;
use App\Mcp\Tools\UpsertCitation;
use App\Mcp\Tools\UpsertConcerns;
use App\Mcp\Tools\UpsertProject;
use App\Mcp\Tools\UpsertSource;
use App\Mcp\Tools\UpsertStakeholder;
use App\Mcp\Tools\WhoAmI;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Intake Server')]
#[Version('0.1.0')]
#[Instructions('Capture project intent, stakeholders, concerns, sources, citations, and initial capabilities.')]
class IntakeServer extends Server
{
    use RoleServerDefaults;

    protected array $tools = [
        WhoAmI::class,
        UpsertProject::class,
        ListProjects::class,
        DeleteProject::class,
        UpsertStakeholder::class,
        DeleteStakeholder::class,
        UpsertConcerns::class,
        DeleteConcern::class,
        UpsertSource::class,
        ListSources::class,
        DeleteSource::class,
        UpsertCitation::class,
        ListCitations::class,
        DeleteCitation::class,
        UpsertCapabilities::class,
        ListCapabilities::class,
        DeleteCapability::class,
        LintCapabilities::class,
        LookupTerm::class,
        TraceQuery::class,
    ];

    protected array $resources = [
        PlaybookResource::class,
        RigorLevelsResource::class,
        ProjectIndexResource::class,
        IntentResource::class,
        CapabilitiesResource::class,
    ];

    protected array $prompts = [
        StartProject::class,
        CaptureIntent::class,
    ];
}
