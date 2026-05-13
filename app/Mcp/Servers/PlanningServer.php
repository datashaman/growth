<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\PlanSlice;
use App\Mcp\Resources\CapabilitiesResource;
use App\Mcp\Resources\PlanResource;
use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\AssignRole;
use App\Mcp\Tools\AssignWorkItemRaci;
use App\Mcp\Tools\BaselinePlan;
use App\Mcp\Tools\BulkLink;
use App\Mcp\Tools\ComparePlanBaseline;
use App\Mcp\Tools\DeleteAgent;
use App\Mcp\Tools\DeleteDeployment;
use App\Mcp\Tools\DeletePlan;
use App\Mcp\Tools\DeleteRelease;
use App\Mcp\Tools\LinkWorkItemDependency;
use App\Mcp\Tools\LinkWorkItemToCapabilities;
use App\Mcp\Tools\LinkWorkItemToMilestone;
use App\Mcp\Tools\LintBaselines;
use App\Mcp\Tools\ListAgents;
use App\Mcp\Tools\ListCapabilities;
use App\Mcp\Tools\ListDeliveryLinks;
use App\Mcp\Tools\ListPlanBaselines;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\Plan\DeleteMilestone;
use App\Mcp\Tools\Plan\DeleteRisk;
use App\Mcp\Tools\Plan\DeleteRole;
use App\Mcp\Tools\Plan\DeleteWorkItem;
use App\Mcp\Tools\Plan\ListDeployments;
use App\Mcp\Tools\Plan\ListMilestones;
use App\Mcp\Tools\Plan\ListReleases;
use App\Mcp\Tools\Plan\ListRisks;
use App\Mcp\Tools\Plan\ListRoles;
use App\Mcp\Tools\Plan\ListWorkItems;
use App\Mcp\Tools\Plan\UpsertDeployment;
use App\Mcp\Tools\Plan\UpsertMilestone;
use App\Mcp\Tools\Plan\UpsertRelease;
use App\Mcp\Tools\SummarizeImplementationStatus;
use App\Mcp\Tools\SummarizePlanCapacity;
use App\Mcp\Tools\SummarizeScheduleHealth;
use App\Mcp\Tools\Trace\TraceQuery;
use App\Mcp\Tools\UnassignRole;
use App\Mcp\Tools\UnassignWorkItemRaci;
use App\Mcp\Tools\UnlinkWorkItemDependency;
use App\Mcp\Tools\UnlinkWorkItemFromCapability;
use App\Mcp\Tools\UnlinkWorkItemFromMilestone;
use App\Mcp\Tools\UpsertAgent;
use App\Mcp\Tools\UpsertDeliveryLink;
use App\Mcp\Tools\UpsertPlan;
use App\Mcp\Tools\UpsertRisk;
use App\Mcp\Tools\UpsertRole;
use App\Mcp\Tools\UpsertWorkItems;
use App\Mcp\Tools\WhoAmI;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Planning Server')]
#[Version('0.1.0')]
#[Instructions('Plan delivery, roles, agents, milestones, work items, risks, releases, and deployments.')]
class PlanningServer extends Server
{
    use RoleServerDefaults;

    protected array $tools = [
        WhoAmI::class,
        ListProjects::class,
        ListCapabilities::class,
        UpsertPlan::class,
        DeletePlan::class,
        BaselinePlan::class,
        ComparePlanBaseline::class,
        ListPlanBaselines::class,
        LintBaselines::class,
        UpsertMilestone::class,
        ListMilestones::class,
        DeleteMilestone::class,
        UpsertRole::class,
        ListRoles::class,
        DeleteRole::class,
        UpsertAgent::class,
        ListAgents::class,
        DeleteAgent::class,
        AssignRole::class,
        UnassignRole::class,
        UpsertWorkItems::class,
        ListWorkItems::class,
        DeleteWorkItem::class,
        LinkWorkItemToCapabilities::class,
        BulkLink::class,
        UnlinkWorkItemFromCapability::class,
        LinkWorkItemToMilestone::class,
        UnlinkWorkItemFromMilestone::class,
        LinkWorkItemDependency::class,
        UnlinkWorkItemDependency::class,
        AssignWorkItemRaci::class,
        UnassignWorkItemRaci::class,
        UpsertDeliveryLink::class,
        ListDeliveryLinks::class,
        UpsertRisk::class,
        ListRisks::class,
        DeleteRisk::class,
        UpsertRelease::class,
        ListReleases::class,
        DeleteRelease::class,
        UpsertDeployment::class,
        ListDeployments::class,
        DeleteDeployment::class,
        SummarizeImplementationStatus::class,
        SummarizePlanCapacity::class,
        SummarizeScheduleHealth::class,
        TraceQuery::class,
    ];

    protected array $resources = [
        PlaybookResource::class,
        ProjectIndexResource::class,
        CapabilitiesResource::class,
        PlanResource::class,
    ];

    protected array $prompts = [
        PlanSlice::class,
    ];
}
