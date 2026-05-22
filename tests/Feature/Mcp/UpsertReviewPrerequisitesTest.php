<?php

use App\Growth\Artifacts\ArtifactRegistry;
use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Tools\Reviews\UpsertReview;
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
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Reviewy',
        'rigor_level' => 2,
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
        })->where('review_brief', fn (string $uri): bool => str_starts_with($uri, 'growth://reviews/') && str_ends_with($uri, '/review-brief'))->etc();
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

it('rejects a change_request target with a message naming the valid types and the review_id linkage', function () {
    $response = GovernanceServer::tool(UpsertReview::class, [
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'Wrong target',
        'targets' => [['type' => 'change_request', 'id' => '01jzzzzzzzzzzzzzzzzzzzzzzz']],
    ]);

    $response->assertHasErrors(['Each review target type must be one of: requirement, concern, design_view, design_element, test_plan, test_case, anomaly, project_plan, milestone, work_item, risk, review_plan. A change request is not a review target — link the review to it via the change request\'s review_id (upsert-change-request) instead.']);
});

it('accepts a valid reviewable target type', function () {
    $response = GovernanceServer::tool(UpsertReview::class, [
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'Right target',
        'targets' => [['type' => 'requirement', 'id' => $this->requirement->id]],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('targets', 1)->etc();
    });
});

it('exposes the review target type enum in the upsert-review tool schema', function () {
    $tools = $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    $reviewTool = collect($tools)->firstWhere('name', 'upsert-review');
    $typeSchema = $reviewTool['inputSchema']['properties']['targets']['items']['properties']['type'] ?? null;

    expect($typeSchema)->not->toBeNull()
        ->and($typeSchema['enum'] ?? null)->toBe(array_keys(ArtifactRegistry::types()));
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
