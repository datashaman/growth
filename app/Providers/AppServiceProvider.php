<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\Anomaly;
use App\Models\ArtifactRelation;
use App\Models\ChangeApprovalEvent;
use App\Models\ChangeImpact;
use App\Models\ChangeRequest;
use App\Models\CheckRunEvidence;
use App\Models\Citation;
use App\Models\Concern;
use App\Models\CustomViewpoint;
use App\Models\Deployment;
use App\Models\DesignElement;
use App\Models\DesignView;
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
use App\Models\Stakeholder;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRun;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public const MORPH_MAP = [
        'requirement' => Requirement::class,
        'artifact_relation' => ArtifactRelation::class,
        'change_request' => ChangeRequest::class,
        'change_approval_event' => ChangeApprovalEvent::class,
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
        'user' => User::class,
    ];

    private const OWNED_RULES = [
        'owned_project' => [Project::class,         'projects.id'],
        'owned_artifact_relation' => [ArtifactRelation::class, 'artifact_relations.id'],
        'owned_change_approval_event' => [ChangeApprovalEvent::class, 'change_approval_events.id'],
        'owned_change_request' => [ChangeRequest::class,   'change_requests.id'],
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
        'owned_release' => [Release::class,         'releases.id'],
        'owned_milestone' => [Milestone::class,       'milestones.id'],
        'owned_role' => [Role::class,            'roles.id'],
        'owned_work_item' => [WorkItem::class,        'work_items.id'],
        'owned_work_item_delivery_link' => [WorkItemDeliveryLink::class, 'work_item_delivery_links.id'],
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
        //
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
