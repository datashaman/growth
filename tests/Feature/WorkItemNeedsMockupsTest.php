<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\UpsertWorkItems;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

test('upsert-work-items persists the needs_mockups flag', function () {
    PlanningServer::tool(UpsertWorkItems::class, [
        'items' => [
            [
                'project_id' => $this->project->id,
                'kind' => 'task',
                'name' => 'Checkout',
                'needs_mockups' => true,
            ],
        ],
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('items.0.ok', true)->etc());

    expect(WorkItem::sole()->needs_mockups)->toBeTrue();
});

test('needs_mockups defaults to false when the flag is omitted', function () {
    PlanningServer::tool(UpsertWorkItems::class, [
        'items' => [
            ['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'No mockups needed'],
        ],
    ])->assertOk();

    expect(WorkItem::sole()->needs_mockups)->toBeFalse();
});

test('upsert-work-items can toggle needs_mockups on an existing item', function () {
    $item = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Toggle me',
        'needs_mockups' => true,
    ]);

    PlanningServer::tool(UpsertWorkItems::class, [
        'items' => [
            [
                'id' => $item->id,
                'project_id' => $this->project->id,
                'kind' => 'task',
                'name' => 'Toggle me',
                'needs_mockups' => false,
            ],
        ],
    ])->assertOk();

    expect($item->refresh()->needs_mockups)->toBeFalse();
});

test('the work item detail page shows the needs-mockups flag', function () {
    $this->actingAs($this->user);

    $item = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Has mockups',
        'status' => 'todo',
        'needs_mockups' => true,
    ]);

    Livewire::test('pages::work-items.show', ['workItem' => $item])
        ->assertSeeInOrder([__('Needs mockups'), __('Yes')]);
});
