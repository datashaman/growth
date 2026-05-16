<?php

use App\Models\Deployment;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

test('delivery links keep a deterministic order when created_at ties', function () {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'name' => 'Ship the lander',
        'kind' => 'task',
        'status' => 'in_progress',
    ]);

    // ULID ids are monotonic, so PR-3 is the newest. Collide created_at so
    // that sort key alone cannot decide the order — the id tiebreaker must.
    $first = WorkItemDeliveryLink::create(['work_item_id' => $workItem->id, 'type' => 'pull_request', 'ref' => 'PR-1']);
    $second = WorkItemDeliveryLink::create(['work_item_id' => $workItem->id, 'type' => 'pull_request', 'ref' => 'PR-2']);
    $third = WorkItemDeliveryLink::create(['work_item_id' => $workItem->id, 'type' => 'pull_request', 'ref' => 'PR-3']);

    WorkItemDeliveryLink::query()->update(['created_at' => now()]);

    Livewire::test('pages::evidence')
        ->assertSeeInOrder([$third->ref, $second->ref, $first->ref]);
});

test('deployments keep a deterministic order when deployed_at ties', function () {
    // Planned deployments all have a null deployed_at, so they tie on every
    // date order key — the id tiebreaker must decide the sequence.
    $first = Deployment::create(['project_id' => $this->project->id, 'environment' => 'alpha', 'status' => 'planned']);
    $second = Deployment::create(['project_id' => $this->project->id, 'environment' => 'beta', 'status' => 'planned']);
    $third = Deployment::create(['project_id' => $this->project->id, 'environment' => 'gamma', 'status' => 'planned']);

    Livewire::test('pages::evidence')
        ->assertSeeInOrder([$third->environment, $second->environment, $first->environment]);
});
