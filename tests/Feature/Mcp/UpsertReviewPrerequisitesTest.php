<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Tools\UpsertReview;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\ReviewParticipant;
use App\Models\ReviewPlan;
use App\Models\Role;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Reviewy',
        'integrity_level' => 2,
    ]);

    $this->requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The app shall remind the user often.',
        'priority' => 'medium',
    ]);
});

it('lists every prerequisite a freshly-created review is missing', function () {
    $response = GovernanceServer::tool(UpsertReview::class, [
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'Initial review',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('missing_prerequisites', function ($missing) {
            $joined = implode("\n", $missing->all());

            return $missing->count() === 4
                && str_contains($joined, 'targets:')
                && str_contains($joined, 'participants:')
                && str_contains($joined, 'entry_criteria:')
                && str_contains($joined, 'exit_criteria:');
        })->etc();
    });
});

it('reports no missing prerequisites once targets, participants, and criteria are present', function () {
    $review = Review::create([
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'Set up review',
        'status' => 'planned',
        'entry_criteria' => ['inputs ready'],
        'exit_criteria' => ['decision recorded'],
    ]);
    $review->targets()->create([
        'reviewable_type' => 'requirement',
        'reviewable_id' => $this->requirement->id,
    ]);
    $role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Reviewer',
    ]);
    ReviewParticipant::create([
        'review_id' => $review->id,
        'role_id' => $role->id,
        'responsibility' => 'reviewer',
        'attendance_status' => 'invited',
    ]);

    $response = GovernanceServer::tool(UpsertReview::class, [
        'id' => $review->id,
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'Set up review',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('missing_prerequisites', [])->etc();
    });
});

it('flags missing inspection roles when type is inspection', function () {
    $response = GovernanceServer::tool(UpsertReview::class, [
        'project_id' => $this->project->id,
        'type' => 'inspection',
        'title' => 'Inspection',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('missing_prerequisites', function ($missing) {
            $joined = implode("\n", $missing->all());

            return str_contains($joined, 'moderator')
                && str_contains($joined, 'reviewer')
                && str_contains($joined, 'recorder');
        })->etc();
    });
});

it('flags expected responsibilities declared by the review plan when participants do not cover them', function () {
    $plan = ReviewPlan::create([
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'name' => 'TR plan',
        'objective' => 'check the thing',
        'procedure' => 'walk through artifact',
        'entry_criteria' => ['inputs ready'],
        'exit_criteria' => ['decision recorded'],
        'expected_responsibilities' => ['approver'],
    ]);

    $response = GovernanceServer::tool(UpsertReview::class, [
        'project_id' => $this->project->id,
        'review_plan_id' => $plan->id,
        'type' => 'technical_review',
        'title' => 'Plan-driven',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('missing_prerequisites', function ($missing) {
            return str_contains(implode("\n", $missing->all()), 'approver');
        })->etc();
    });
});
