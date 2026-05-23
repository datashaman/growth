<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Changes\GetChangeRequest;
use App\Models\ChangeApprovalEvent;
use App\Models\ChangeImpact;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestDeliveryLink;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Change details',
        'rigor_level' => 2,
    ]);
});

it('returns full change request details by id', function () {
    $requester = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Product Owner',
    ]);
    $review = Review::create([
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'Telemetry review',
        'status' => 'held',
        'decision' => 'accepted_with_actions',
    ]);
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The dashboard shall expose telemetry evidence.',
    ]);
    $change = ChangeRequest::create([
        'project_id' => $this->project->id,
        'requester_role_id' => $requester->id,
        'review_id' => $review->id,
        'title' => 'Document telemetry evidence',
        'description' => 'Capture the evidence context.',
        'rationale' => 'Operators need the decision trail.',
        'category' => 'requirements',
        'status' => 'approved',
        'priority' => 'high',
        'decision' => 'approved',
        'decision_rationale' => 'Low risk documentation update.',
        'decided_at' => now(),
    ]);
    ChangeImpact::create([
        'change_request_id' => $change->id,
        'impactable_type' => 'requirement',
        'impactable_id' => $requirement->id,
        'impact_kind' => 'references',
        'description' => 'Context only.',
    ]);
    ChangeApprovalEvent::create([
        'change_request_id' => $change->id,
        'recorded_by_user_id' => $this->user->id,
        'from_status' => 'under_review',
        'to_status' => 'approved',
        'to_decision' => 'approved',
        'rationale' => 'Approved by CCB.',
        'recorded_at' => now(),
    ]);
    ChangeRequestDeliveryLink::create([
        'change_request_id' => $change->id,
        'type' => 'pull_request',
        'ref' => '#43',
        'url' => 'https://github.com/datashaman/growth/pull/43',
    ]);

    GovernanceServer::tool(GetChangeRequest::class, ['id' => $change->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($change, $requester, $review, $requirement) {
            $json->where('id', $change->id)
                ->where('reference', $change->reference())
                ->where('title', 'Document telemetry evidence')
                ->where('description', 'Capture the evidence context.')
                ->where('rationale', 'Operators need the decision trail.')
                ->where('category', 'requirements')
                ->where('status', 'approved')
                ->where('priority', 'high')
                ->where('decision', 'approved')
                ->where('decision_rationale', 'Low risk documentation update.')
                ->where('requester_role.id', $requester->id)
                ->where('review.id', $review->id)
                ->where('review.decision', 'accepted_with_actions')
                ->where('impacts.0.impactable_id', $requirement->id)
                ->where('impacts.0.artifact.reference', $requirement->reference())
                ->where('impacts.0.impact_kind', 'references')
                ->where('approval_events.0.to_decision', 'approved')
                ->where('approval_events.0.recorded_by', $this->user->name)
                ->where('delivery_links.0.ref', '#43')
                ->where('change_impact_brief', "growth://change-requests/{$change->id}/change-impact-brief")
                ->etc();
        });
});

it('returns a change request by project reference through readonly and planning surfaces', function () {
    $change = ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Reference lookup',
        'category' => 'scope',
    ]);

    foreach ([ReadonlyServer::class, PlanningServer::class] as $server) {
        $server::tool(GetChangeRequest::class, [
            'project_id' => $this->project->id,
            'reference' => 'CR-'.$change->number,
        ])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('id', $change->id)
                ->where('reference', $change->reference())
                ->etc());
    }
});

it('errors for a missing change request reference', function () {
    GovernanceServer::tool(GetChangeRequest::class, [
        'project_id' => $this->project->id,
        'reference' => 'CR-999',
    ])->assertHasErrors(['No change request matching']);
});

it('does not return a change request from another workspace', function () {
    $other = User::factory()->create();
    $foreignProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);
    $foreignChange = ChangeRequest::create([
        'project_id' => $foreignProject->id,
        'title' => 'Foreign change',
        'category' => 'scope',
    ]);

    GovernanceServer::tool(GetChangeRequest::class, ['id' => $foreignChange->id])
        ->assertHasErrors(['No change request matching']);
});
