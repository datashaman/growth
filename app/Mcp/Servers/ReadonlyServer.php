<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\ArchitectureResource;
use App\Mcp\Resources\EvidenceResource;
use App\Mcp\Resources\GateStatusApp;
use App\Mcp\Resources\IntentResource;
use App\Mcp\Resources\PlanResource;
use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\Project\ProjectChangesResource;
use App\Mcp\Resources\Project\ProjectMtpResource;
use App\Mcp\Resources\Project\ProjectPmpResource;
use App\Mcp\Resources\Project\ProjectReviewsResource;
use App\Mcp\Resources\Project\ProjectSddResource;
use App\Mcp\Resources\Project\ProjectSourcesResource;
use App\Mcp\Resources\Project\ProjectSrsResource;
use App\Mcp\Resources\ProjectDashboardApp;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\ReadinessResource;
use App\Mcp\Resources\RequirementExplorerApp;
use App\Mcp\Resources\RequirementsResource;
use App\Mcp\Resources\RigorLevelsResource;
use App\Mcp\Resources\TraceGraphApp;
use App\Mcp\Resources\VerificationResource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\Architecture\ListArchitectureElements;
use App\Mcp\Tools\Architecture\ListArchitectureViewpoints;
use App\Mcp\Tools\Architecture\ListArchitectureViews;
use App\Mcp\Tools\Assurance\BuildEvidenceBundle;
use App\Mcp\Tools\Assurance\EvaluateReadinessGates;
use App\Mcp\Tools\Changes\ListArtifactRelations;
use App\Mcp\Tools\Changes\ListChangeApprovalEvents;
use App\Mcp\Tools\Changes\ListChangeRequests;
use App\Mcp\Tools\Common\Doctor;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Dashboard\GetProjectDashboardData;
use App\Mcp\Tools\Dashboard\ShowGateStatus;
use App\Mcp\Tools\Dashboard\ShowProjectDashboard;
use App\Mcp\Tools\Dashboard\ShowRequirementExplorer;
use App\Mcp\Tools\Dashboard\ShowTraceGraph;
use App\Mcp\Tools\Feedback\ListToolInvocations;
use App\Mcp\Tools\Feedback\SearchFeedback;
use App\Mcp\Tools\Feedback\SendFeedback;
use App\Mcp\Tools\Glossary\LookupTerm;
use App\Mcp\Tools\Lint\LintProject;
use App\Mcp\Tools\Plan\ListAgents;
use App\Mcp\Tools\Plan\ListCheckRuns;
use App\Mcp\Tools\Plan\ListDeliveryLinks;
use App\Mcp\Tools\Plan\ListDeployments;
use App\Mcp\Tools\Plan\ListMilestones;
use App\Mcp\Tools\Plan\ListPlanBaselines;
use App\Mcp\Tools\Plan\ListReleases;
use App\Mcp\Tools\Plan\ListRisks;
use App\Mcp\Tools\Plan\ListRoles;
use App\Mcp\Tools\Plan\ListWorkItems;
use App\Mcp\Tools\Plan\SummarizeImplementationStatus;
use App\Mcp\Tools\Plan\SummarizePlanCapacity;
use App\Mcp\Tools\Plan\SummarizeScheduleHealth;
use App\Mcp\Tools\Projects\ListProjects;
use App\Mcp\Tools\Requirements\ListRequirements;
use App\Mcp\Tools\Reviews\ListReviewDecisionEvents;
use App\Mcp\Tools\Reviews\ListReviewFindings;
use App\Mcp\Tools\Reviews\ListReviewParticipants;
use App\Mcp\Tools\Reviews\ListReviewPlans;
use App\Mcp\Tools\Reviews\ListReviews;
use App\Mcp\Tools\Sources\ListCitations;
use App\Mcp\Tools\Sources\ListSources;
use App\Mcp\Tools\Trace\TraceQuery;
use App\Mcp\Tools\Verification\ListAnomalies;
use App\Mcp\Tools\Verification\ListVerificationCases;
use App\Mcp\Tools\Verification\ListVerificationPlans;
use App\Mcp\Tools\Verification\ListVerificationRuns;
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
        Doctor::class,
        SearchFeedback::class,
        SendFeedback::class,
        ListProjects::class,
        ListRequirements::class,
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
        ListToolInvocations::class,
        SummarizeImplementationStatus::class,
        SummarizePlanCapacity::class,
        SummarizeScheduleHealth::class,
        BuildEvidenceBundle::class,
        EvaluateReadinessGates::class,
        LintProject::class,
        LookupTerm::class,
        TraceQuery::class,
        ShowProjectDashboard::class,
        ShowGateStatus::class,
        ShowRequirementExplorer::class,
        ShowTraceGraph::class,
        GetProjectDashboardData::class,
    ];

    protected array $resources = [
        ProjectDashboardApp::class,
        GateStatusApp::class,
        RequirementExplorerApp::class,
        TraceGraphApp::class,
        PlaybookResource::class,
        RigorLevelsResource::class,
        ProjectIndexResource::class,
        IntentResource::class,
        RequirementsResource::class,
        ArchitectureResource::class,
        VerificationResource::class,
        PlanResource::class,
        EvidenceResource::class,
        ReadinessResource::class,
        ProjectSrsResource::class,
        ProjectSddResource::class,
        ProjectMtpResource::class,
        ProjectPmpResource::class,
        ProjectSourcesResource::class,
        ProjectChangesResource::class,
        ProjectReviewsResource::class,
    ];
}
