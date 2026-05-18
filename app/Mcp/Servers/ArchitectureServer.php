<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\ArchitectureResource;
use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\RequirementsResource;
use App\Mcp\Resources\RigorLevelsResource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\Architecture\DeleteArchitectureElement;
use App\Mcp\Tools\Architecture\DeleteArchitectureView;
use App\Mcp\Tools\Architecture\DeleteArchitectureViewpoint;
use App\Mcp\Tools\Architecture\ListArchitectureElements;
use App\Mcp\Tools\Architecture\ListArchitectureViewpoints;
use App\Mcp\Tools\Architecture\ListArchitectureViews;
use App\Mcp\Tools\Architecture\UpsertArchitectureElements;
use App\Mcp\Tools\Architecture\UpsertArchitectureView;
use App\Mcp\Tools\Architecture\UpsertArchitectureViewpoint;
use App\Mcp\Tools\Common\BulkLink;
use App\Mcp\Tools\Common\Doctor;
use App\Mcp\Tools\Common\ListUsers;
use App\Mcp\Tools\Common\SendNotification;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Feedback\GetFeedback;
use App\Mcp\Tools\Feedback\ReopenFeedback;
use App\Mcp\Tools\Feedback\ResolveFeedback;
use App\Mcp\Tools\Feedback\SearchFeedback;
use App\Mcp\Tools\Feedback\SendFeedback;
use App\Mcp\Tools\Feedback\TriageFeedback;
use App\Mcp\Tools\Lint\LintProject;
use App\Mcp\Tools\Projects\ListProjects;
use App\Mcp\Tools\Requirements\ListRequirements;
use App\Mcp\Tools\Search\Search;
use App\Mcp\Tools\Trace\TraceQuery;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Architecture Server')]
#[Version('0.1.0')]
class ArchitectureServer extends Server
{
    use RoleServerDefaults;

    protected array $tools = [
        WhoAmI::class,
        ListUsers::class,
        SendNotification::class,
        Search::class,
        Doctor::class,
        GetFeedback::class,
        SearchFeedback::class,
        SendFeedback::class,
        TriageFeedback::class,
        ResolveFeedback::class,
        ReopenFeedback::class,
        ListProjects::class,
        ListRequirements::class,
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
        LintProject::class,
        TraceQuery::class,
    ];

    protected array $resources = [
        PlaybookResource::class,
        RigorLevelsResource::class,
        ProjectIndexResource::class,
        RequirementsResource::class,
        ArchitectureResource::class,
    ];
}
