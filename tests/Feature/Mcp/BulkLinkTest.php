<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Common\BulkLink;
use App\Models\Concern;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Linky',
        'rigor_level' => 2,
    ]);

    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Build it',
    ]);

    $this->requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The app shall do the thing repeatedly.',
        'priority' => 'medium',
    ]);

    $this->milestone = Milestone::create([
        'project_id' => $this->project->id,
        'name' => 'M1',
    ]);

    $this->view = DesignView::create([
        'project_id' => $this->project->id,
        'viewpoint' => 'logical',
        'name' => 'V1',
    ]);

    $this->concern = Concern::create([
        'project_id' => $this->project->id,
        'text' => 'A real concern statement.',
    ]);
});

it('attaches work-item to requirements, work-item to milestones, and concerns to view in one batch', function () {
    $response = PlanningServer::tool(BulkLink::class, [
        'items' => [
            [
                'link_type' => 'work_item_to_requirements',
                'from_id' => $this->workItem->id,
                'to_ids' => [$this->requirement->id],
            ],
            [
                'link_type' => 'work_item_to_milestones',
                'from_id' => $this->workItem->id,
                'to_ids' => [$this->milestone->id],
            ],
            [
                'link_type' => 'concerns_to_view',
                'from_id' => $this->view->id,
                'to_ids' => [$this->concern->id],
            ],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('items', 3)
            ->where('items.0.ok', true)
            ->where('items.0.link_type', 'work_item_to_requirements')
            ->where('items.0.attached', 1)
            ->where('items.1.ok', true)
            ->where('items.1.link_type', 'work_item_to_milestones')
            ->where('items.1.attached', 1)
            ->where('items.2.ok', true)
            ->where('items.2.link_type', 'concerns_to_view')
            ->where('items.2.attached', 1)
            ->etc();
    });

    expect($this->workItem->fresh()->requirements()->count())->toBe(1);
    expect($this->workItem->fresh()->milestones()->count())->toBe(1);
    expect($this->view->fresh()->concerns()->count())->toBe(1);
});

it('reports per-tuple errors without aborting the batch', function () {
    $response = PlanningServer::tool(BulkLink::class, [
        'items' => [
            [
                'link_type' => 'work_item_to_requirements',
                'from_id' => $this->workItem->id,
                'to_ids' => [$this->requirement->id],
            ],
            [
                'link_type' => 'work_item_to_requirements',
                'from_id' => $this->workItem->id,
                'to_ids' => ['01nonexistentrequirement00'],
            ],
            [
                'link_type' => 'invalid_type',
                'from_id' => $this->workItem->id,
                'to_ids' => [$this->requirement->id],
            ],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('items', 3)
            ->where('items.0.ok', true)
            ->where('items.1.ok', false)
            ->has('items.1.errors')
            ->where('items.2.ok', false)
            ->has('items.2.errors.link_type')
            ->etc();
    });
});

it('rejects linking a work item to a milestone in another project', function () {
    $otherProject = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Elsewhere',
        'rigor_level' => 2,
    ]);
    $otherMilestone = Milestone::create([
        'project_id' => $otherProject->id,
        'name' => 'Cross',
    ]);

    $response = PlanningServer::tool(BulkLink::class, [
        'items' => [
            [
                'link_type' => 'work_item_to_milestones',
                'from_id' => $this->workItem->id,
                'to_ids' => [$otherMilestone->id],
            ],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('items', 1)
            ->where('items.0.ok', false)
            ->has('items.0.errors.to_ids')
            ->etc();
    });

    expect($this->workItem->fresh()->milestones()->count())->toBe(0);
});

it('rejects an empty items array', function () {
    PlanningServer::tool(BulkLink::class, ['items' => []])->assertHasErrors();
});

it('rejects more than 100 link items in a single call', function () {
    $items = array_fill(0, 101, [
        'link_type' => 'work_item_to_requirements',
        'from_id' => $this->workItem->id,
        'to_ids' => [$this->requirement->id],
    ]);

    PlanningServer::tool(BulkLink::class, ['items' => $items])
        ->assertHasErrors(['Batches are capped at 100 items per call. Split into smaller batches.']);
});
