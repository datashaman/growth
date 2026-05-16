<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\CheckReadiness;
use App\Mcp\Resources\EvidenceResource;
use App\Mcp\Resources\GateStatusApp;
use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\ReadinessResource;
use App\Mcp\Resources\RigorLevelsResource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\Assurance\AssessReleaseReadiness;
use App\Mcp\Tools\Assurance\BuildEvidenceBundle;
use App\Mcp\Tools\Assurance\EvaluateReadinessGates;
use App\Mcp\Tools\Assurance\ReportEvidenceGaps;
use App\Mcp\Tools\Assurance\ScanContradictions;
use App\Mcp\Tools\Changes\AnalyzeChangeImpact;
use App\Mcp\Tools\Changes\DeleteChangeRequest;
use App\Mcp\Tools\Changes\ListArtifactRelations;
use App\Mcp\Tools\Changes\ListChangeApprovalEvents;
use App\Mcp\Tools\Changes\ListChangeRequests;
use App\Mcp\Tools\Changes\UpsertArtifactRelation;
use App\Mcp\Tools\Changes\UpsertChangeRequest;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Dashboard\ShowGateStatus;
use App\Mcp\Tools\Lint\LintProject;
use App\Mcp\Tools\Projects\ListProjects;
use App\Mcp\Tools\Reviews\AcceptFinding;
use App\Mcp\Tools\Reviews\CloseFinding;
use App\Mcp\Tools\Reviews\DeleteReview;
use App\Mcp\Tools\Reviews\DeleteReviewFinding;
use App\Mcp\Tools\Reviews\DeleteReviewParticipant;
use App\Mcp\Tools\Reviews\DeleteReviewPlan;
use App\Mcp\Tools\Reviews\DispositionFinding;
use App\Mcp\Tools\Reviews\ListReviewDecisionEvents;
use App\Mcp\Tools\Reviews\ListReviewFindings;
use App\Mcp\Tools\Reviews\ListReviewParticipants;
use App\Mcp\Tools\Reviews\ListReviewPlans;
use App\Mcp\Tools\Reviews\ListReviews;
use App\Mcp\Tools\Reviews\ReopenFinding;
use App\Mcp\Tools\Reviews\ResolveFinding;
use App\Mcp\Tools\Reviews\UpsertReview;
use App\Mcp\Tools\Reviews\UpsertReviewFinding;
use App\Mcp\Tools\Reviews\UpsertReviewParticipant;
use App\Mcp\Tools\Reviews\UpsertReviewPlan;
use App\Mcp\Tools\Trace\TraceQuery;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Governance Server')]
#[Version('0.1.0')]
#[Instructions('Manage reviews, change control, release readiness, impact analysis, and evidence gaps.')]
class GovernanceServer extends Server
{
    use RoleServerDefaults;

    protected array $tools = [
        WhoAmI::class,
        ListProjects::class,
        UpsertReviewPlan::class,
        ListReviewPlans::class,
        DeleteReviewPlan::class,
        UpsertReview::class,
        ListReviews::class,
        ListReviewDecisionEvents::class,
        DeleteReview::class,
        UpsertReviewParticipant::class,
        ListReviewParticipants::class,
        DeleteReviewParticipant::class,
        UpsertReviewFinding::class,
        ListReviewFindings::class,
        DeleteReviewFinding::class,
        DispositionFinding::class,
        ResolveFinding::class,
        AcceptFinding::class,
        CloseFinding::class,
        ReopenFinding::class,
        AnalyzeChangeImpact::class,
        UpsertArtifactRelation::class,
        ListArtifactRelations::class,
        UpsertChangeRequest::class,
        ListChangeRequests::class,
        ListChangeApprovalEvents::class,
        DeleteChangeRequest::class,
        AssessReleaseReadiness::class,
        ReportEvidenceGaps::class,
        ScanContradictions::class,
        EvaluateReadinessGates::class,
        BuildEvidenceBundle::class,
        LintProject::class,
        TraceQuery::class,
        ShowGateStatus::class,
    ];

    protected array $resources = [
        PlaybookResource::class,
        RigorLevelsResource::class,
        ProjectIndexResource::class,
        EvidenceResource::class,
        ReadinessResource::class,
        GateStatusApp::class,
    ];

    protected array $prompts = [
        CheckReadiness::class,
    ];
}
