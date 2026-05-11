<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\ArchitectureResource;
use App\Mcp\Resources\CapabilitiesResource;
use App\Mcp\Resources\EvidenceResource;
use App\Mcp\Resources\IntentResource;
use App\Mcp\Resources\PlanResource;
use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectDashboardApp;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\ReadinessResource;
use App\Mcp\Resources\VerificationResource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\BuildEvidenceBundle;
use App\Mcp\Tools\Changes\ListArtifactRelations;
use App\Mcp\Tools\Changes\ListChangeApprovalEvents;
use App\Mcp\Tools\Changes\ListChangeRequests;
use App\Mcp\Tools\EvaluateReadinessGates;
use App\Mcp\Tools\GetProjectDashboardData;
use App\Mcp\Tools\ListAgents;
use App\Mcp\Tools\ListAnomalies;
use App\Mcp\Tools\ListArchitectureElements;
use App\Mcp\Tools\ListArchitectureViewpoints;
use App\Mcp\Tools\ListArchitectureViews;
use App\Mcp\Tools\ListCapabilities;
use App\Mcp\Tools\ListCheckRuns;
use App\Mcp\Tools\ListCitations;
use App\Mcp\Tools\ListDeliveryLinks;
use App\Mcp\Tools\ListPlanBaselines;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ListReviews;
use App\Mcp\Tools\ListVerificationCases;
use App\Mcp\Tools\ListVerificationPlans;
use App\Mcp\Tools\ListVerificationRuns;
use App\Mcp\Tools\LookupTerm;
use App\Mcp\Tools\Plan\ListDeployments;
use App\Mcp\Tools\Plan\ListMilestones;
use App\Mcp\Tools\Plan\ListReleases;
use App\Mcp\Tools\Plan\ListRisks;
use App\Mcp\Tools\Plan\ListRoles;
use App\Mcp\Tools\Plan\ListWorkItems;
use App\Mcp\Tools\Reviews\ListReviewDecisionEvents;
use App\Mcp\Tools\Reviews\ListReviewFindings;
use App\Mcp\Tools\Reviews\ListReviewParticipants;
use App\Mcp\Tools\Reviews\ListReviewPlans;
use App\Mcp\Tools\ShowProjectDashboard;
use App\Mcp\Tools\Sources\ListSources;
use App\Mcp\Tools\SummarizeImplementationStatus;
use App\Mcp\Tools\SummarizePlanCapacity;
use App\Mcp\Tools\SummarizeScheduleHealth;
use App\Mcp\Tools\Trace\TraceQuery;
use App\Mcp\Tools\WhoAmI;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Readonly Server')]
#[Version('0.1.0')]
#[Instructions('Read project state, summaries, traces, resources, and lookup terms without mutating project data.')]
class ReadonlyServer extends Server
{
    use RoleServerDefaults;

    protected array $tools = [
        WhoAmI::class,
        ListProjects::class,
        ListCapabilities::class,
        ListSources::class,
        ListCitations::class,
        ListArchitectureViewpoints::class,
        ListArchitectureViews::class,
        ListArchitectureElements::class,
        ListVerificationPlans::class,
        ListVerificationCases::class,
        ListVerificationRuns::class,
        ListAnomalies::class,
        ListPlanBaselines::class,
        ListMilestones::class,
        ListRoles::class,
        ListAgents::class,
        ListWorkItems::class,
        ListDeliveryLinks::class,
        ListCheckRuns::class,
        ListRisks::class,
        ListReleases::class,
        ListDeployments::class,
        ListReviews::class,
        ListReviewPlans::class,
        ListReviewParticipants::class,
        ListReviewFindings::class,
        ListReviewDecisionEvents::class,
        ListArtifactRelations::class,
        ListChangeRequests::class,
        ListChangeApprovalEvents::class,
        SummarizeImplementationStatus::class,
        SummarizePlanCapacity::class,
        SummarizeScheduleHealth::class,
        BuildEvidenceBundle::class,
        EvaluateReadinessGates::class,
        LookupTerm::class,
        TraceQuery::class,
        ShowProjectDashboard::class,
        GetProjectDashboardData::class,
    ];

    protected array $resources = [
        ProjectDashboardApp::class,
        PlaybookResource::class,
        ProjectIndexResource::class,
        IntentResource::class,
        CapabilitiesResource::class,
        ArchitectureResource::class,
        VerificationResource::class,
        PlanResource::class,
        EvidenceResource::class,
        ReadinessResource::class,
    ];
}
