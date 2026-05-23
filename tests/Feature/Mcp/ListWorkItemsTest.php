<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\ListWorkItems;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Discovery',
        'rigor_level' => 2,
    ]);
});

it('returns mockup discovery fields on work item rows', function () {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Needs a mockup',
        'needs_mockups' => true,
    ]);
    createMockup($workItem, 'Default layout', '<!doctype html><html></html>');

    PlanningServer::tool(ListWorkItems::class, [
        'project_id' => $this->project->id,
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('results.0.id', $workItem->id)
            ->where('results.0.needs_mockups', true)
            ->where('results.0.mockups_count', 1)
            ->etc());
});

it('filters work items by needs_mockups and mockup presence', function () {
    $missing = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Checkout missing mockup',
        'needs_mockups' => true,
    ]);
    $covered = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Dashboard covered by mockup',
        'needs_mockups' => true,
    ]);
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Backend task',
        'needs_mockups' => false,
    ]);
    createMockup($covered, 'Dashboard layout', '<!doctype html><html></html>');

    PlanningServer::tool(ListWorkItems::class, [
        'project_id' => $this->project->id,
        'needs_mockups' => true,
        'has_mockups' => false,
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 1)
            ->where('results.0.id', $missing->id)
            ->where('results.0.needs_mockups', true)
            ->where('results.0.mockups_count', 0)
            ->etc());

    PlanningServer::tool(ListWorkItems::class, [
        'project_id' => $this->project->id,
        'has_mockups' => true,
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 1)
            ->where('results.0.id', $covered->id)
            ->where('results.0.mockups_count', 1)
            ->etc());
});

it('matches work items by reference in the text query', function (string $query) {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Reference target',
    ]);
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Other work',
    ]);

    PlanningServer::tool(ListWorkItems::class, [
        'project_id' => $this->project->id,
        'q' => $query,
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 1)
            ->where('results.0.id', $workItem->id)
            ->where('results.0.reference', $workItem->reference())
            ->etc());
})->with([
    'full reference' => 'WI-001',
    'lowercase reference' => 'wi-001',
    'bare number' => '1',
]);

it('returns work items in WI reference order instead of kind, name, or status order', function () {
    $first = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Zulu done task',
        'status' => 'done',
    ]);
    $second = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Alpha todo deliverable',
        'status' => 'todo',
    ]);

    PlanningServer::tool(ListWorkItems::class, [
        'project_id' => $this->project->id,
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('results.0.id', $first->id)
            ->where('results.1.id', $second->id)
            ->etc());
});

it('keeps existing pagination bounded with the new filters', function () {
    foreach (range(1, 3) as $index) {
        WorkItem::create([
            'project_id' => $this->project->id,
            'kind' => 'task',
            'name' => "Mockup item {$index}",
            'needs_mockups' => true,
        ]);
    }

    PlanningServer::tool(ListWorkItems::class, [
        'project_id' => $this->project->id,
        'needs_mockups' => true,
        'limit' => 2,
        'offset' => 1,
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 3)
            ->where('limit', 2)
            ->where('offset', 1)
            ->has('results', 2)
            ->etc());
});

it('does not list work items from another workspace', function () {
    $other = User::factory()->create();
    $foreignProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);

    PlanningServer::tool(ListWorkItems::class, [
        'project_id' => $foreignProject->id,
    ])->assertHasErrors();
});

it('documents the mockup discovery filters in the tool schema', function () {
    $tools = $this->postJson('/mcp/planning', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    $tool = collect($tools)->firstWhere('name', 'list-work-items');

    expect($tool)->not->toBeNull()
        ->and($tool['description'] ?? '')->toContain('needs_mockups')
        ->and($tool['description'] ?? '')->toContain('mockups_count')
        ->and($tool['inputSchema']['properties'] ?? [])->toHaveKeys(['needs_mockups', 'has_mockups'])
        ->and($tool['inputSchema']['properties']['q']['description'] ?? '')->toContain('WI-019');
});
