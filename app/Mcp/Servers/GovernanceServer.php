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
use App\Mcp\Tools\Assurance\RecommendRigorLevel;
use App\Mcp\Tools\Assurance\ReportEvidenceGaps;
use App\Mcp\Tools\Assurance\ScanContradictions;
use App\Mcp\Tools\Changes\AnalyzeChangeImpact;
use App\Mcp\Tools\Changes\ApproveChangeRequest;
use App\Mcp\Tools\Changes\CancelChangeRequest;
use App\Mcp\Tools\Changes\DeferChangeRequest;
use App\Mcp\Tools\Changes\DeleteChangeRequest;
use App\Mcp\Tools\Changes\ListArtifactRelations;
use App\Mcp\Tools\Changes\ListChangeApprovalEvents;
use App\Mcp\Tools\Changes\ListChangeRequests;
use App\Mcp\Tools\Changes\MarkChangeRequestImplemented;
use App\Mcp\Tools\Changes\RejectChangeRequest;
use App\Mcp\Tools\Changes\SubmitChangeRequest;
use App\Mcp\Tools\Changes\SubscribeChangeRequest;
use App\Mcp\Tools\Changes\UnsubscribeChangeRequest;
use App\Mcp\Tools\Changes\UpsertArtifactRelation;
use App\Mcp\Tools\Changes\UpsertChangeRequest;
use App\Mcp\Tools\Common\Doctor;
use App\Mcp\Tools\Common\ListNotifications;
use App\Mcp\Tools\Common\ListUsers;
use App\Mcp\Tools\Common\MarkNotificationRead;
use App\Mcp\Tools\Common\ReplyToNotification;
use App\Mcp\Tools\Common\SendNotification;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Dashboard\ShowGateStatus;
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
use App\Mcp\Tools\Projects\ListProjects;
use App\Mcp\Tools\Reviews\AcceptFinding;
use App\Mcp\Tools\Reviews\CancelReview;
use App\Mcp\Tools\Reviews\CloseFinding;
use App\Mcp\Tools\Reviews\CloseReview;
use App\Mcp\Tools\Reviews\DeleteReview;
use App\Mcp\Tools\Reviews\DeleteReviewFinding;
use App\Mcp\Tools\Reviews\DeleteReviewParticipant;
use App\Mcp\Tools\Reviews\DeleteReviewPlan;
use App\Mcp\Tools\Reviews\DispositionFinding;
use App\Mcp\Tools\Reviews\HoldReview;
use App\Mcp\Tools\Reviews\ListReviewDecisionEvents;
use App\Mcp\Tools\Reviews\ListReviewFindings;
use App\Mcp\Tools\Reviews\ListReviewParticipants;
use App\Mcp\Tools\Reviews\ListReviewPlans;
use App\Mcp\Tools\Reviews\ListReviews;
use App\Mcp\Tools\Reviews\ReopenFinding;
use App\Mcp\Tools\Reviews\ResolveFinding;
use App\Mcp\Tools\Reviews\StartReview;
use App\Mcp\Tools\Reviews\UpsertReview;
use App\Mcp\Tools\Reviews\UpsertReviewFinding;
use App\Mcp\Tools\Reviews\UpsertReviewParticipant;
use App\Mcp\Tools\Reviews\UpsertReviewPlan;
use App\Mcp\Tools\Search\Search;
use App\Mcp\Tools\Trace\TraceQuery;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Governance Server')]
#[Version('0.1.0')]
class GovernanceServer extends Server
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
        ReplyToNotification::class,
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
        UpsertReviewPlan::class,
        ListReviewPlans::class,
        DeleteReviewPlan::class,
        UpsertReview::class,
        ListReviews::class,
        ListReviewDecisionEvents::class,
        DeleteReview::class,
        StartReview::class,
        HoldReview::class,
        CloseReview::class,
        CancelReview::class,
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
        SubmitChangeRequest::class,
        ApproveChangeRequest::class,
        RejectChangeRequest::class,
        DeferChangeRequest::class,
        MarkChangeRequestImplemented::class,
        CancelChangeRequest::class,
        ListChangeRequests::class,
        ListChangeApprovalEvents::class,
        SubscribeChangeRequest::class,
        UnsubscribeChangeRequest::class,
        DeleteChangeRequest::class,
        AssessReleaseReadiness::class,
        ReportEvidenceGaps::class,
        ScanContradictions::class,
        EvaluateReadinessGates::class,
        RecommendRigorLevel::class,
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
