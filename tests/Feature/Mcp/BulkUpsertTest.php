<?php

use App\Mcp\Servers\ArchitectureServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\VerificationServer;
use App\Mcp\Tools\Architecture\UpsertArchitectureElements;
use App\Mcp\Tools\Concerns\UpsertConcerns;
use App\Mcp\Tools\Plan\UpsertWorkItems;
use App\Mcp\Tools\Requirements\UpsertRequirements;
use App\Mcp\Tools\Verification\UpsertVerificationCases;
use App\Models\DesignView;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\TestPlan;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Bulky',
        'rigor_level' => 2,
    ]);
});

it('upserts a batch of work items with mixed success and failure', function () {
    $response = PlanningServer::tool(UpsertWorkItems::class, [
        'items' => [
            [
                'project_id' => $this->project->id,
                'kind' => WorkItem::KINDS[0],
                'name' => 'Good item',
            ],
            [
                'project_id' => $this->project->id,
                'kind' => 'not-a-kind',
                'name' => 'Bad kind',
            ],
            [
                'project_id' => $this->project->id,
                'kind' => WorkItem::KINDS[0],
                'name' => 'Another good item',
            ],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('items', 3)
            ->where('items.0.ok', true)
            ->where('items.1.ok', false)
            ->has('items.1.errors.kind')
            ->where('items.2.ok', true)
            ->etc();
    });

    expect(WorkItem::where('project_id', $this->project->id)->count())->toBe(2);
});

it('upserts a batch of concerns with mixed success and failure', function () {
    $response = IntakeServer::tool(UpsertConcerns::class, [
        'items' => [
            ['project_id' => $this->project->id, 'text' => 'A real concern statement.'],
            ['project_id' => $this->project->id, 'text' => 'no'],
            ['project_id' => $this->project->id, 'text' => 'Another real concern.'],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('items', 3)
            ->where('items.0.ok', true)
            ->where('items.1.ok', false)
            ->has('items.1.errors.text')
            ->where('items.2.ok', true)
            ->etc();
    });
});

it('upserts a batch of architecture elements with mixed success and failure', function () {
    $view = DesignView::create([
        'project_id' => $this->project->id,
        'viewpoint' => 'logical',
        'name' => 'V1',
    ]);

    $response = ArchitectureServer::tool(UpsertArchitectureElements::class, [
        'items' => [
            ['design_view_id' => $view->id, 'kind' => 'entity', 'name' => 'User'],
            ['design_view_id' => $view->id, 'kind' => 'not-real', 'name' => 'X'],
            ['design_view_id' => $view->id, 'kind' => 'attribute', 'name' => 'email'],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('items', 3)
            ->where('items.0.ok', true)
            ->where('items.1.ok', false)
            ->has('items.1.errors.kind')
            ->where('items.2.ok', true)
            ->etc();
    });
});

it('upserts a batch of verification cases with mixed success and failure and syncs requirements', function () {
    $cap = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The app shall do the thing repeatedly.',
        'priority' => 'medium',
    ]);
    $plan = TestPlan::create([
        'project_id' => $this->project->id,
        'level' => 'unit',
        'name' => 'Unit',
        'scope' => 's',
        'approach' => 'a',
    ]);

    $response = VerificationServer::tool(UpsertVerificationCases::class, [
        'items' => [
            [
                'test_plan_id' => $plan->id,
                'name' => 'Happy path',
                'expected_results' => 'It works.',
                'verifies_requirement_ids' => [$cap->id],
            ],
            [
                'test_plan_id' => $plan->id,
                'name' => 'No caps',
                'expected_results' => 'It works.',
                'verifies_requirement_ids' => [],
            ],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('items', 2)
            ->where('items.0.ok', true)
            ->where('items.0.requirements_verified', 1)
            ->where('items.1.ok', false)
            ->has('items.1.errors.verifies_requirement_ids')
            ->etc();
    });
});

it('rejects an empty items array', function () {
    IntakeServer::tool(UpsertRequirements::class, ['items' => []])
        ->assertHasErrors();
});

it('rejects more than 100 items in a single call', function () {
    $items = array_fill(0, 101, [
        'project_id' => $this->project->id,
        'layer' => 'software',
        'type' => 'functional',
        'text' => 'The app shall do the thing repeatedly.',
    ]);

    IntakeServer::tool(UpsertRequirements::class, ['items' => $items])
        ->assertHasErrors(['Batches are capped at 100 items per call. Split into smaller batches.']);
});
