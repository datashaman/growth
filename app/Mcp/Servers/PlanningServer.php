<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\PlanSlice;
use App\Mcp\Resources\PlanResource;
use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\RequirementsResource;
use App\Mcp\Resources\RigorLevelsResource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\Common\BulkLink;
use App\Mcp\Tools\Common\Doctor;
use App\Mcp\Tools\Common\ListNotifications;
use App\Mcp\Tools\Common\ListUsers;
use App\Mcp\Tools\Common\MarkNotificationRead;
use App\Mcp\Tools\Common\SendNotification;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Dashboard\SummarizeMyQueue;
use App\Mcp\Tools\Decisions\AnswerDecisionRequest;
use App\Mcp\Tools\Decisions\CancelDecisionRequest;
use App\Mcp\Tools\Decisions\CreateDecisionRequest;
use App\Mcp\Tools\Decisions\ListDecisionQueue;
use App\Mcp\Tools\Feedback\CommentFeedback;
use App\Mcp\Tools\Feedback\GetFeedback;
use App\Mcp\Tools\Feedback\ReopenFeedback;
use App\Mcp\Tools\Feedback\ResolveFeedback;
use App\Mcp\Tools\Feedback\SearchFeedback;
use App\Mcp\Tools\Feedback\SendFeedback;
use App\Mcp\Tools\Feedback\TriageFeedback;
use App\Mcp\Tools\Lint\LintProject;
use App\Mcp\Tools\Plan\AcceptRisk;
use App\Mcp\Tools\Plan\AchieveMilestone;
use App\Mcp\Tools\Plan\ActivatePlan;
use App\Mcp\Tools\Plan\AssessRisk;
use App\Mcp\Tools\Plan\AssignRole;
use App\Mcp\Tools\Plan\AssignWorkItemRaci;
use App\Mcp\Tools\Plan\BaselinePlan;
use App\Mcp\Tools\Plan\BlockWorkItem;
use App\Mcp\Tools\Plan\CancelDeployment;
use App\Mcp\Tools\Plan\CancelRelease;
use App\Mcp\Tools\Plan\CancelWorkItem;
use App\Mcp\Tools\Plan\ClosePlan;
use App\Mcp\Tools\Plan\CloseRisk;
use App\Mcp\Tools\Plan\ComparePlanBaseline;
use App\Mcp\Tools\Plan\CompleteWorkItem;
use App\Mcp\Tools\Plan\DeleteAgent;
use App\Mcp\Tools\Plan\DeleteDeployment;
use App\Mcp\Tools\Plan\DeleteMilestone;
use App\Mcp\Tools\Plan\DeleteMockup;
use App\Mcp\Tools\Plan\DeletePlan;
use App\Mcp\Tools\Plan\DeleteRelease;
use App\Mcp\Tools\Plan\DeleteRisk;
use App\Mcp\Tools\Plan\DeleteRole;
use App\Mcp\Tools\Plan\DeleteWorkItem;
use App\Mcp\Tools\Plan\LinkWorkItemDependency;
use App\Mcp\Tools\Plan\LinkWorkItemToMilestone;
use App\Mcp\Tools\Plan\LinkWorkItemToRequirements;
use App\Mcp\Tools\Plan\ListAgents;
use App\Mcp\Tools\Plan\ListDeliveryLinks;
use App\Mcp\Tools\Plan\ListDeployments;
use App\Mcp\Tools\Plan\ListMilestones;
use App\Mcp\Tools\Plan\ListMockupRevisions;
use App\Mcp\Tools\Plan\ListMockups;
use App\Mcp\Tools\Plan\ListPlanBaselines;
use App\Mcp\Tools\Plan\ListProjectPlans;
use App\Mcp\Tools\Plan\ListReleases;
use App\Mcp\Tools\Plan\ListRisks;
use App\Mcp\Tools\Plan\ListRoles;
use App\Mcp\Tools\Plan\ListWorkItems;
use App\Mcp\Tools\Plan\MarkDeploymentFailed;
use App\Mcp\Tools\Plan\MarkDeploymentSucceeded;
use App\Mcp\Tools\Plan\MarkReleaseReleased;
use App\Mcp\Tools\Plan\MarkRiskMitigated;
use App\Mcp\Tools\Plan\MarkRiskRealized;
use App\Mcp\Tools\Plan\PromoteRelease;
use App\Mcp\Tools\Plan\RecordUnattributedEvent;
use App\Mcp\Tools\Plan\ReopenWorkItem;
use App\Mcp\Tools\Plan\ResolveWorkItemByBranch;
use App\Mcp\Tools\Plan\ResolveWorkItemByReference;
use App\Mcp\Tools\Plan\RevertMockup;
use App\Mcp\Tools\Plan\RollBackDeployment;
use App\Mcp\Tools\Plan\StartDeployment;
use App\Mcp\Tools\Plan\StartRiskMitigation;
use App\Mcp\Tools\Plan\StartWorkItem;
use App\Mcp\Tools\Plan\SummarizeImplementationStatus;
use App\Mcp\Tools\Plan\UnassignRole;
use App\Mcp\Tools\Plan\UnassignWorkItemRaci;
use App\Mcp\Tools\Plan\UnblockWorkItem;
use App\Mcp\Tools\Plan\UnlinkWorkItemDependency;
use App\Mcp\Tools\Plan\UnlinkWorkItemFromMilestone;
use App\Mcp\Tools\Plan\UnlinkWorkItemFromRequirement;
use App\Mcp\Tools\Plan\UpsertAgent;
use App\Mcp\Tools\Plan\UpsertDeliveryLink;
use App\Mcp\Tools\Plan\UpsertDeployment;
use App\Mcp\Tools\Plan\UpsertMilestone;
use App\Mcp\Tools\Plan\UpsertMockup;
use App\Mcp\Tools\Plan\UpsertPlan;
use App\Mcp\Tools\Plan\UpsertRelease;
use App\Mcp\Tools\Plan\UpsertRisk;
use App\Mcp\Tools\Plan\UpsertRole;
use App\Mcp\Tools\Plan\UpsertWorkItems;
use App\Mcp\Tools\Projects\ListProjects;
use App\Mcp\Tools\Requirements\ListRequirements;
use App\Mcp\Tools\Search\Search;
use App\Mcp\Tools\Trace\TraceQuery;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Planning Server')]
#[Version('0.1.0')]
class PlanningServer extends Server
{
    use RoleServerDefaults;

    protected array $tools = [
        WhoAmI::class,
        CreateDecisionRequest::class,
        ListDecisionQueue::class,
        SummarizeMyQueue::class,
        AnswerDecisionRequest::class,
        CancelDecisionRequest::class,
        ListNotifications::class,
        ListUsers::class,
        MarkNotificationRead::class,
        SendNotification::class,
        Search::class,
        Doctor::class,
        GetFeedback::class,
        SearchFeedback::class,
        CommentFeedback::class,
        SendFeedback::class,
        TriageFeedback::class,
        ResolveFeedback::class,
        ReopenFeedback::class,
        ListProjects::class,
        ListRequirements::class,
        UpsertPlan::class,
        DeletePlan::class,
        ListProjectPlans::class,
        BaselinePlan::class,
        ActivatePlan::class,
        ClosePlan::class,
        ComparePlanBaseline::class,
        ListPlanBaselines::class,
        UpsertMilestone::class,
        ListMilestones::class,
        DeleteMilestone::class,
        AchieveMilestone::class,
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
        StartWorkItem::class,
        CompleteWorkItem::class,
        BlockWorkItem::class,
        UnblockWorkItem::class,
        CancelWorkItem::class,
        ReopenWorkItem::class,
        LinkWorkItemToRequirements::class,
        BulkLink::class,
        UnlinkWorkItemFromRequirement::class,
        LinkWorkItemToMilestone::class,
        UnlinkWorkItemFromMilestone::class,
        LinkWorkItemDependency::class,
        UnlinkWorkItemDependency::class,
        ResolveWorkItemByBranch::class,
        ResolveWorkItemByReference::class,
        RecordUnattributedEvent::class,
        AssignWorkItemRaci::class,
        UnassignWorkItemRaci::class,
        UpsertDeliveryLink::class,
        ListDeliveryLinks::class,
        UpsertMockup::class,
        ListMockups::class,
        ListMockupRevisions::class,
        RevertMockup::class,
        DeleteMockup::class,
        UpsertRisk::class,
        ListRisks::class,
        DeleteRisk::class,
        AssessRisk::class,
        StartRiskMitigation::class,
        MarkRiskMitigated::class,
        AcceptRisk::class,
        MarkRiskRealized::class,
        CloseRisk::class,
        UpsertRelease::class,
        PromoteRelease::class,
        MarkReleaseReleased::class,
        CancelRelease::class,
        ListReleases::class,
        DeleteRelease::class,
        UpsertDeployment::class,
        StartDeployment::class,
        MarkDeploymentSucceeded::class,
        MarkDeploymentFailed::class,
        RollBackDeployment::class,
        CancelDeployment::class,
        ListDeployments::class,
        DeleteDeployment::class,
        SummarizeImplementationStatus::class,
        LintProject::class,
        TraceQuery::class,
    ];

    protected array $resources = [
        PlaybookResource::class,
        RigorLevelsResource::class,
        ProjectIndexResource::class,
        RequirementsResource::class,
        PlanResource::class,
    ];

    protected array $prompts = [
        PlanSlice::class,
    ];
}
