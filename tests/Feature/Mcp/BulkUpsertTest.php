<?php

use App\Mcp\Servers\ArchitectureServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\VerificationServer;
use App\Mcp\Tools\Architecture\DeleteArchitectureElements;
use App\Mcp\Tools\Architecture\UpsertArchitectureElements;
use App\Mcp\Tools\Concerns\DeleteConcerns;
use App\Mcp\Tools\Concerns\UpsertConcerns;
use App\Mcp\Tools\Plan\DeleteWorkItems;
use App\Mcp\Tools\Plan\UpsertWorkItems;
use App\Mcp\Tools\Requirements\DeleteRequirements;
use App\Mcp\Tools\Requirements\UpsertRequirements;
use App\Mcp\Tools\Verification\DeleteVerificationCases;
use App\Mcp\Tools\Verification\UpsertVerificationCases;
use App\Models\Concern;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\TestCase;
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

it('deletes concerns by id filter', function () {
    $first = Concern::create(['project_id' => $this->project->id, 'text' => 'First concern statement.']);
    $second = Concern::create(['project_id' => $this->project->id, 'text' => 'Second concern statement.']);
    $kept = Concern::create(['project_id' => $this->project->id, 'text' => 'Kept concern statement.']);

    IntakeServer::tool(DeleteConcerns::class, ['id' => [$first->id, $second->id]])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($first, $second) {
            $json->where('filters.id', [$first->id, $second->id])
                ->where('deleted_count', 2)
                ->where('deleted.0.id', $first->id)
                ->where('deleted.1.id', $second->id)
                ->etc();
        });

    expect(Concern::whereKey([$first->id, $second->id])->exists())->toBeFalse()
        ->and($kept->fresh())->not->toBeNull();
});

it('deletes requirements by id filter and reports detached children', function () {
    $parent = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall group child requirements.',
    ]);
    $child = Requirement::create([
        'project_id' => $this->project->id,
        'parent_id' => $parent->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall detach this child requirement.',
    ]);
    $other = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall delete another requirement.',
    ]);

    IntakeServer::tool(DeleteRequirements::class, ['id' => [$parent->id, $other->id]])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($parent, $other) {
            $json->where('deleted_count', 2)
                ->where('deleted.0.id', $parent->id)
                ->where('deleted.0.children_detached', 1)
                ->where('deleted.1.id', $other->id)
                ->etc();
        });

    expect(Requirement::whereKey([$parent->id, $other->id])->exists())->toBeFalse()
        ->and($child->fresh()->parent_id)->toBeNull();
});

it('deletes architecture elements by id filter', function () {
    $view = DesignView::create([
        'project_id' => $this->project->id,
        'viewpoint' => 'logical',
        'name' => 'Logical view',
    ]);
    $first = DesignElement::create(['design_view_id' => $view->id, 'kind' => 'entity', 'name' => 'First']);
    $second = DesignElement::create(['design_view_id' => $view->id, 'kind' => 'entity', 'name' => 'Second']);
    $kept = DesignElement::create(['design_view_id' => $view->id, 'kind' => 'entity', 'name' => 'Kept']);

    ArchitectureServer::tool(DeleteArchitectureElements::class, ['id' => [$first->id, $second->id]])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($first, $second) {
            $json->where('deleted_count', 2)
                ->where('deleted.0.id', $first->id)
                ->where('deleted.1.id', $second->id)
                ->etc();
        });

    expect(DesignElement::whereKey([$first->id, $second->id])->exists())->toBeFalse()
        ->and($kept->fresh())->not->toBeNull();
});

it('deletes work items by id filter and reports dropped links', function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall link work items to requirements.',
    ]);
    $parent = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Parent task',
    ]);
    $child = WorkItem::create([
        'project_id' => $this->project->id,
        'parent_id' => $parent->id,
        'kind' => 'task',
        'name' => 'Child task',
    ]);
    $other = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Other task',
    ]);
    $parent->requirements()->attach($requirement->id);

    PlanningServer::tool(DeleteWorkItems::class, ['id' => [$parent->id, $other->id]])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($parent, $other) {
            $json->where('deleted_count', 2)
                ->where('deleted.0.id', $parent->id)
                ->where('deleted.0.children_orphaned', 1)
                ->where('deleted.0.requirement_links_dropped', 1)
                ->where('deleted.1.id', $other->id)
                ->etc();
        });

    expect(WorkItem::whereKey([$parent->id, $other->id])->exists())->toBeFalse()
        ->and($child->fresh()->parent_id)->toBeNull();
});

it('deletes verification cases by id filter and reports unlinked requirements', function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall verify requirement coverage.',
    ]);
    $plan = TestPlan::create([
        'project_id' => $this->project->id,
        'level' => 'unit',
        'name' => 'Unit',
        'scope' => 's',
        'approach' => 'a',
    ]);
    $first = TestCase::create([
        'test_plan_id' => $plan->id,
        'name' => 'First case',
        'expected_results' => 'It passes.',
    ]);
    $second = TestCase::create([
        'test_plan_id' => $plan->id,
        'name' => 'Second case',
        'expected_results' => 'It passes.',
    ]);
    $kept = TestCase::create([
        'test_plan_id' => $plan->id,
        'name' => 'Kept case',
        'expected_results' => 'It passes.',
    ]);
    $first->requirements()->attach($requirement->id);

    VerificationServer::tool(DeleteVerificationCases::class, ['id' => [$first->id, $second->id]])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($first, $second) {
            $json->where('deleted_count', 2)
                ->where('deleted.0.id', $first->id)
                ->where('deleted.0.requirements_unlinked', 1)
                ->where('deleted.1.id', $second->id)
                ->etc();
        });

    expect(TestCase::whereKey([$first->id, $second->id])->exists())->toBeFalse()
        ->and($kept->fresh())->not->toBeNull();
});

it('rejects duplicate ids in a delete filter', function () {
    $concern = Concern::create(['project_id' => $this->project->id, 'text' => 'Repeated concern statement.']);

    IntakeServer::tool(DeleteConcerns::class, ['id' => [$concern->id, $concern->id]])
        ->assertHasErrors();
});

it('exposes plural delete tools alongside plural upsert tools', function () {
    $surfaces = [
        '/mcp/intake' => [
            'present' => ['delete-concerns', 'delete-requirements'],
            'absent' => ['delete-concern', 'delete-requirement'],
        ],
        '/mcp/architecture' => [
            'present' => ['delete-architecture-elements'],
            'absent' => ['delete-architecture-element'],
        ],
        '/mcp/planning' => [
            'present' => ['delete-work-items'],
            'absent' => ['delete-work-item'],
        ],
        '/mcp/verification' => [
            'present' => ['delete-verification-cases'],
            'absent' => ['delete-verification-case'],
        ],
    ];

    foreach ($surfaces as $endpoint => $expectations) {
        $tools = $this->postJson($endpoint, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => ['per_page' => 200],
        ])->assertOk()->json('result.tools');

        $names = collect($tools)->pluck('name')->all();

        expect($names)
            ->toContain(...$expectations['present'])
            ->not->toContain(...$expectations['absent']);
    }
});
