<?php

namespace App\Providers;

use App\Mcp\RecordingCallTool;
use App\Models\Agent;
use App\Models\Anomaly;
use App\Models\ArtifactRelation;
use App\Models\ChangeApprovalEvent;
use App\Models\ChangeImpact;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestDeliveryLink;
use App\Models\CheckRunEvidence;
use App\Models\Citation;
use App\Models\Concern;
use App\Models\CustomViewpoint;
use App\Models\DecisionRequest;
use App\Models\Deployment;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\EvidenceAsset;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\ProjectPlanBaseline;
use App\Models\Release;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\ReviewFinding;
use App\Models\ReviewParticipant;
use App\Models\ReviewPlan;
use App\Models\ReviewTarget;
use App\Models\Risk;
use App\Models\Role;
use App\Models\Source;
use App\Models\Mockup;
use App\Models\Stakeholder;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRun;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\ToolFeedback;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use App\Support\AgentContext;
use App\Support\OAuthWorkspaceBinding;
use App\Support\Passport\AccessTokenRepository;
use App\Support\Passport\AuthCodeRepository;
use App\Support\Passport\RefreshTokenRepository;
use App\Support\RoleContext;
use App\Support\SurfaceContext;
use App\Support\WorkspaceContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Passport\Bridge\AccessTokenRepository as BridgeAccessTokenRepository;
use Laravel\Passport\Bridge\AuthCodeRepository as BridgeAuthCodeRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository as BridgeRefreshTokenRepository;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public const MORPH_MAP = [
        'project' => Project::class,
        'requirement' => Requirement::class,
        'artifact_relation' => ArtifactRelation::class,
        'change_request' => ChangeRequest::class,
        'change_request_delivery_link' => ChangeRequestDeliveryLink::class,
        'change_approval_event' => ChangeApprovalEvent::class,
        'decision_request' => DecisionRequest::class,
        'check_run_evidence' => CheckRunEvidence::class,
        'concern' => Concern::class,
        'deployment' => Deployment::class,
        'design_view' => DesignView::class,
        'design_element' => DesignElement::class,
        'custom_viewpoint' => CustomViewpoint::class,
        'test_plan' => TestPlan::class,
        'test_case' => TestCase::class,
        'test_run' => TestRun::class,
        'anomaly' => Anomaly::class,
        'project_plan' => ProjectPlan::class,
        'theme' => Theme::class,
        'theme_assignment' => ThemeAssignment::class,
        'release' => Release::class,
        'milestone' => Milestone::class,
        'role' => Role::class,
        'work_item' => WorkItem::class,
        'work_item_delivery_link' => WorkItemDeliveryLink::class,
        'risk' => Risk::class,
        'review_plan' => ReviewPlan::class,
        'review' => Review::class,
        'review_finding' => ReviewFinding::class,
        'agent' => Agent::class,
        'tool_feedback' => ToolFeedback::class,
        'user' => User::class,
    ];

    private const OWNED_RULES = [
        'owned_project' => [Project::class,         'projects.id'],
        'owned_project_repo' => [Project::class,    'projects.github_repo'],
        'owned_artifact_relation' => [ArtifactRelation::class, 'artifact_relations.id'],
        'owned_change_approval_event' => [ChangeApprovalEvent::class, 'change_approval_events.id'],
        'owned_change_request' => [ChangeRequest::class,   'change_requests.id'],
        'owned_change_request_delivery_link' => [ChangeRequestDeliveryLink::class, 'change_request_delivery_links.id'],
        'owned_decision_request' => [DecisionRequest::class, 'decision_requests.id'],
        'owned_change_impact' => [ChangeImpact::class,    'change_impacts.id'],
        'owned_check_run_evidence' => [CheckRunEvidence::class, 'check_run_evidences.id'],
        'owned_requirement' => [Requirement::class,     'requirements.id'],
        'owned_stakeholder' => [Stakeholder::class,     'stakeholders.id'],
        'owned_concern' => [Concern::class,         'concerns.id'],
        'owned_custom_viewpoint' => [CustomViewpoint::class, 'custom_viewpoints.id'],
        'owned_deployment' => [Deployment::class,      'deployments.id'],
        'owned_design_view' => [DesignView::class,      'design_views.id'],
        'owned_design_element' => [DesignElement::class,   'design_elements.id'],
        'owned_test_plan' => [TestPlan::class,        'test_plans.id'],
        'owned_test_case' => [TestCase::class,        'test_cases.id'],
        'owned_test_run' => [TestRun::class,         'test_runs.id'],
        'owned_anomaly' => [Anomaly::class,         'anomalies.id'],
        'owned_source' => [Source::class,          'sources.id'],
        'owned_citation' => [Citation::class,        'citations.id'],
        'owned_project_plan' => [ProjectPlan::class,     'project_plans.id'],
        'owned_project_plan_baseline' => [ProjectPlanBaseline::class, 'project_plan_baselines.id'],
        'owned_theme' => [Theme::class,    'themes.id'],
        'owned_theme_assignment' => [ThemeAssignment::class, 'theme_assignments.id'],
        'owned_release' => [Release::class,         'releases.id'],
        'owned_milestone' => [Milestone::class,       'milestones.id'],
        'owned_mockup' => [Mockup::class,        'mockups.id'],
        'owned_role' => [Role::class,            'roles.id'],
        'owned_work_item' => [WorkItem::class,        'work_items.id'],
        'owned_work_item_delivery_link' => [WorkItemDeliveryLink::class, 'work_item_delivery_links.id'],
        'owned_evidence_asset' => [EvidenceAsset::class, 'evidence_assets.id'],
        'owned_risk' => [Risk::class,            'risks.id'],
        'owned_review_plan' => [ReviewPlan::class,      'review_plans.id'],
        'owned_review' => [Review::class,          'reviews.id'],
        'owned_review_finding' => [ReviewFinding::class,   'review_findings.id'],
        'owned_review_participant' => [ReviewParticipant::class, 'review_participants.id'],
        'owned_review_target' => [ReviewTarget::class,    'review_targets.id'],
        'owned_agent' => [Agent::class,           'agents.id'],
    ];

    public function register(): void
    {
        $this->app->singleton(WorkspaceContext::class);
        $this->app->singleton(SurfaceContext::class);
        $this->app->singleton(RoleContext::class);
        $this->app->singleton(AgentContext::class);
        // Scoped, not singleton: the holder carries mutable per-request state
        // and must reset between requests on long-lived workers (#197, #214).
        $this->app->scoped(OAuthWorkspaceBinding::class);
        $this->app->bind(CallTool::class, RecordingCallTool::class);

        // Carry the consent-screen workspace selection through the OAuth grant
        // chain so HTTP MCP tokens are workspace-bound (#197, #214). Passport
        // resolves these bridge repositories from the container when it builds
        // the authorization server.
        $this->app->bind(BridgeAuthCodeRepository::class, AuthCodeRepository::class);
        $this->app->bind(BridgeAccessTokenRepository::class, AccessTokenRepository::class);
        $this->app->bind(BridgeRefreshTokenRepository::class, RefreshTokenRepository::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        Passport::authorizationView(fn (array $parameters) => view('mcp.authorize', $parameters));

        Relation::morphMap(self::MORPH_MAP);

        foreach (self::OWNED_RULES as $rule => [$model, $idColumn]) {
            Validator::extend(
                $rule,
                fn (string $attribute, mixed $value): bool => is_string($value)
                    && $model::where($idColumn, $value)->exists(),
                'The selected :attribute is invalid.',
            );
        }
    }
}
