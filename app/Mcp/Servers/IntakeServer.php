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
use App\Mcp\Tools\Capabilities\DeleteCapability;
use App\Mcp\Tools\Capabilities\LintCapabilities;
use App\Mcp\Tools\Capabilities\ListCapabilities;
use App\Mcp\Tools\Capabilities\UpsertCapabilities;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Concerns\DeleteConcern;
use App\Mcp\Tools\Concerns\UpsertConcerns;
use App\Mcp\Tools\Glossary\LookupTerm;
use App\Mcp\Tools\Projects\DeleteProject;
use App\Mcp\Tools\Projects\ListProjects;
use App\Mcp\Tools\Projects\UpsertProject;
use App\Mcp\Tools\Sources\CiteArtifact;
use App\Mcp\Tools\Sources\DeleteCitation;
use App\Mcp\Tools\Sources\DeleteSource;
use App\Mcp\Tools\Sources\ListCitations;
use App\Mcp\Tools\Sources\ListSources;
use App\Mcp\Tools\Sources\UncitArtifact;
use App\Mcp\Tools\Sources\UpsertCitation;
use App\Mcp\Tools\Sources\UpsertSource;
use App\Mcp\Tools\Stakeholders\DeleteStakeholder;
use App\Mcp\Tools\Stakeholders\UpsertStakeholder;
use App\Mcp\Tools\Trace\TraceQuery;
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
        CiteArtifact::class,
        UncitArtifact::class,
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
