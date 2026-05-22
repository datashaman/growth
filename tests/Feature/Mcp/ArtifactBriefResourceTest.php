<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Models\ChangeApprovalEvent;
use App\Models\ChangeImpact;
use App\Models\ChangeRequest;
use App\Models\Concern;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\ReviewDecisionEvent;
use App\Models\ReviewFinding;
use App\Models\ReviewParticipant;
use App\Models\ReviewPlan;
use App\Models\ReviewTarget;
use App\Models\Role;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRun;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Briefed',
        'rigor_level' => 2,
    ]);
});

it('serves a work item implementation brief', function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The workflow shall show approval state.',
        'acceptance_criteria' => ['Approval state is visible on every item.'],
    ]);
    $role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Developer',
    ]);
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'responsible_role_id' => $role->id,
        'kind' => 'task',
        'name' => 'Build approval state UI',
        'description' => 'Render approval state on work items.',
    ]);
    $workItem->requirements()->attach($requirement->id);
    $workItem->raciRoles()->attach($role->id, ['raci' => 'r']);
    $milestone = Milestone::create([
        'project_id' => $this->project->id,
        'name' => 'Beta',
    ]);
    $workItem->milestones()->attach($milestone->id);

    $concern = Concern::create([
        'project_id' => $this->project->id,
        'text' => 'Approvers need fast status recognition.',
    ]);
    $view = DesignView::create([
        'project_id' => $this->project->id,
        'viewpoint' => 'interaction',
        'name' => 'Approval interaction',
        'description' => 'Approval status flow.',
    ]);
    $view->concerns()->attach($concern->id);
    DesignElement::create([
        'design_view_id' => $view->id,
        'kind' => 'entity',
        'name' => 'Approval badge',
        'type' => 'ui_component',
        'purpose' => 'Shows current approval state.',
    ]);
    createMockup($workItem, 'default', '<!doctype html><html><body>approval</body></html>');
    $delivery = WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'pull_request',
        'ref' => '#42',
    ]);
    $delivery->checkRuns()->create([
        'provider' => 'github-actions',
        'name' => 'tests',
        'status' => 'completed',
        'conclusion' => 'success',
    ]);

    readResource(ReadonlyServer::class, "growth://work-items/{$workItem->id}/implementation-brief")
        ->assertOk()
        ->assertSee('Implementation Brief')
        ->assertSee($workItem->reference())
        ->assertSee('The workflow shall show approval state.')
        ->assertSee('Approval state is visible on every item.')
        ->assertSee('Approval interaction')
        ->assertSee('Approval badge')
        ->assertSee('Developer')
        ->assertSee('Beta')
        ->assertSee('growth://mockups/')
        ->assertSee('#42')
        ->assertSee('tests: completed / success');
});

it('serves a requirement verification brief', function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The checkout shall reject expired cards.',
        'acceptance_criteria' => ['Expired cards show a blocking error.'],
        'renders_ui' => true,
    ]);
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Validate card expiry',
    ]);
    $workItem->requirements()->attach($requirement->id);
    createMockup($requirement, 'default', '<!doctype html><html><body>card error</body></html>');

    $view = DesignView::create([
        'project_id' => $this->project->id,
        'viewpoint' => 'interaction',
        'name' => 'Payment validation',
    ]);
    DesignElement::create([
        'design_view_id' => $view->id,
        'kind' => 'entity',
        'name' => 'Expiry validator',
        'type' => 'service',
    ]);
    $plan = TestPlan::create([
        'project_id' => $this->project->id,
        'level' => 'acceptance',
        'name' => 'Checkout acceptance',
    ]);
    $case = TestCase::create([
        'test_plan_id' => $plan->id,
        'name' => 'Reject expired card',
        'expected_results' => 'A blocking expiry error is shown.',
    ]);
    $case->requirements()->attach($requirement->id);
    $run = TestRun::create([
        'test_case_id' => $case->id,
        'status' => 'fail',
        'run_at' => now(),
    ]);
    $anomaly = $run->anomalies()->create([
        'project_id' => $this->project->id,
        'severity' => 'high',
        'status' => 'open',
        'summary' => 'Expired cards can submit.',
        'description' => 'The form accepts an expired card during checkout.',
    ]);
    $anomaly->affectedRequirements()->attach($requirement->id);

    readResource(ReadonlyServer::class, "growth://requirements/{$requirement->id}/verification-brief")
        ->assertOk()
        ->assertSee('Verification Brief')
        ->assertSee($requirement->reference())
        ->assertSee('The checkout shall reject expired cards.')
        ->assertSee('Expired cards show a blocking error.')
        ->assertSee('Reject expired card')
        ->assertSee('A blocking expiry error is shown.')
        ->assertSee('growth://mockups/')
        ->assertSee('Validate card expiry')
        ->assertSee('Payment validation')
        ->assertSee('Expiry validator')
        ->assertSee('high/open')
        ->assertSee('Expired cards can submit.');
});

it('serves a review brief', function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The audit trail shall show every approval decision.',
        'acceptance_criteria' => ['Approval decisions include actor, time, and rationale.'],
    ]);
    $role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Quality Lead',
    ]);
    $plan = ReviewPlan::create([
        'project_id' => $this->project->id,
        'type' => 'inspection',
        'name' => 'Inspection plan',
        'objective' => 'Confirm captured evidence is enough for release.',
        'procedure' => 'Inspect linked requirements, architecture, and findings.',
        'entry_criteria' => ['Targets are linked.'],
        'exit_criteria' => ['Findings are dispositioned.'],
        'expected_responsibilities' => ['moderator', 'reviewer', 'recorder'],
        'checklist' => ['Check trace coverage.'],
    ]);
    $review = Review::create([
        'project_id' => $this->project->id,
        'review_plan_id' => $plan->id,
        'owner_role_id' => $role->id,
        'type' => 'inspection',
        'title' => 'Approval evidence inspection',
        'objective' => 'Review approval evidence.',
        'status' => 'held',
        'decision' => 'accepted_with_actions',
    ]);
    ReviewTarget::create([
        'review_id' => $review->id,
        'reviewable_type' => 'requirement',
        'reviewable_id' => $requirement->id,
        'context' => 'Decision auditability',
    ]);
    ReviewParticipant::create([
        'review_id' => $review->id,
        'role_id' => $role->id,
        'responsibility' => 'moderator',
        'attendance_status' => 'attended',
    ]);
    ReviewFinding::create([
        'project_id' => $this->project->id,
        'review_id' => $review->id,
        'owner_role_id' => $role->id,
        'reviewable_type' => 'requirement',
        'reviewable_id' => $requirement->id,
        'title' => 'Rationale is underspecified',
        'description' => 'Approval rationale must be visible in the release evidence.',
        'severity' => 'medium',
        'status' => 'open',
    ]);
    ReviewDecisionEvent::create([
        'review_id' => $review->id,
        'recorded_by_user_id' => $this->user->id,
        'from_status' => 'in_progress',
        'to_status' => 'held',
        'to_decision' => 'accepted_with_actions',
        'rationale' => 'Actions captured for release evidence.',
        'recorded_at' => now(),
    ]);

    $view = DesignView::create([
        'project_id' => $this->project->id,
        'viewpoint' => 'logical',
        'name' => 'Approval audit',
    ]);
    DesignElement::create([
        'design_view_id' => $view->id,
        'kind' => 'entity',
        'name' => 'Decision event',
        'type' => 'record',
    ]);
    $change = ChangeRequest::create([
        'project_id' => $this->project->id,
        'review_id' => $review->id,
        'title' => 'Expose approval rationale',
        'category' => 'requirements',
        'priority' => 'medium',
        'status' => 'proposed',
    ]);

    readResource(ReadonlyServer::class, "growth://reviews/{$review->id}/review-brief")
        ->assertOk()
        ->assertSee('Review Brief')
        ->assertSee('Approval evidence inspection')
        ->assertSee('Inspection plan')
        ->assertSee('Targets are linked.')
        ->assertSee('The audit trail shall show every approval decision.')
        ->assertSee('Decision auditability')
        ->assertSee('Quality Lead')
        ->assertSee('Rationale is underspecified')
        ->assertSee('Approval audit')
        ->assertSee('Decision event')
        ->assertSee('Actions captured for release evidence.')
        ->assertSee($change->reference())
        ->assertSee("growth://change-requests/{$change->id}/change-impact-brief");
});

it('serves a change impact brief', function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The approval screen shall expose decision rationale.',
    ]);
    $role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Change Control',
    ]);
    $consultedRole = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Security',
    ]);
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Render rationale column',
    ]);
    $workItem->raciRoles()->attach($consultedRole->id, ['raci' => 'c']);
    $review = Review::create([
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'Approval rationale review',
        'status' => 'held',
        'decision' => 'rework_required',
    ]);
    ReviewTarget::create([
        'review_id' => $review->id,
        'reviewable_type' => 'requirement',
        'reviewable_id' => $requirement->id,
        'context' => 'Rationale display',
    ]);
    ReviewFinding::create([
        'project_id' => $this->project->id,
        'review_id' => $review->id,
        'reviewable_type' => 'requirement',
        'reviewable_id' => $requirement->id,
        'title' => 'UI omits rationale',
        'severity' => 'high',
        'status' => 'open',
    ]);
    $change = ChangeRequest::create([
        'project_id' => $this->project->id,
        'requester_role_id' => $role->id,
        'review_id' => $review->id,
        'title' => 'Add rationale to approvals',
        'description' => 'Expose rationale wherever approvals are listed.',
        'rationale' => 'Review found the evidence is incomplete.',
        'category' => 'requirements',
        'priority' => 'high',
        'status' => 'under_review',
    ]);
    ChangeImpact::create([
        'change_request_id' => $change->id,
        'impactable_type' => 'requirement',
        'impactable_id' => $requirement->id,
        'impact_kind' => 'modifies',
        'description' => 'Adds explicit rationale display behavior.',
    ]);
    ChangeImpact::create([
        'change_request_id' => $change->id,
        'impactable_type' => 'work_item',
        'impactable_id' => $workItem->id,
        'impact_kind' => 'modifies',
        'description' => 'Updates the approval UI implementation.',
    ]);
    ChangeApprovalEvent::create([
        'change_request_id' => $change->id,
        'recorded_by_user_id' => $this->user->id,
        'from_status' => 'proposed',
        'to_status' => 'under_review',
        'rationale' => 'Ready for CCB.',
        'recorded_at' => now(),
    ]);

    $view = DesignView::create([
        'project_id' => $this->project->id,
        'viewpoint' => 'interaction',
        'name' => 'Approval list',
    ]);
    DesignElement::create([
        'design_view_id' => $view->id,
        'kind' => 'entity',
        'name' => 'Rationale column',
        'type' => 'ui',
    ]);

    readResource(ReadonlyServer::class, "growth://change-requests/{$change->id}/change-impact-brief")
        ->assertOk()
        ->assertSee('Change Impact Brief')
        ->assertSee($change->reference())
        ->assertSee('Add rationale to approvals')
        ->assertSee('Change Control')
        ->assertSee('Review found the evidence is incomplete.')
        ->assertSee('modifies')
        ->assertSee('The approval screen shall expose decision rationale.')
        ->assertSee('Adds explicit rationale display behavior.')
        ->assertSee('Render rationale column')
        ->assertSee('Consult with: Security')
        ->assertSee('Approval rationale review')
        ->assertSee("growth://reviews/{$review->id}/review-brief")
        ->assertSee('UI omits rationale')
        ->assertSee('Approval list')
        ->assertSee('Rationale column')
        ->assertSee('Ready for CCB.');
});
