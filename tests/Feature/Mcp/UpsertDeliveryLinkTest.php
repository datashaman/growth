<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\UpsertDeliveryLink;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Delivery',
        'rigor_level' => 2,
    ]);

    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Ship it',
    ]);
});

it('accepts a GitHub pull request payload from the sync action', function () {
    $args = [
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => '#42',
        'url' => 'https://github.com/datashaman/growth/pull/42',
        'description' => 'GitHub pull request: Add widget',
    ];

    PlanningServer::tool(UpsertDeliveryLink::class, $args)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('type', 'pull_request')
                ->where('ref', '#42')
                ->where('created', true)
                ->etc();
        });

    expect(WorkItemDeliveryLink::where('work_item_id', $this->workItem->id)->where('ref', '#42')->count())
        ->toBe(1);
});

it('upserts the same pull request ref across synchronize and merge events', function () {
    $args = [
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => '#42',
        'url' => 'https://github.com/datashaman/growth/pull/42',
        'description' => 'GitHub pull request: Add widget',
    ];

    PlanningServer::tool(UpsertDeliveryLink::class, $args)->assertOk();
    PlanningServer::tool(UpsertDeliveryLink::class, [...$args, 'description' => 'updated'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('created', false)->etc();
        });

    expect(WorkItemDeliveryLink::where('work_item_id', $this->workItem->id)->where('ref', '#42')->count())
        ->toBe(1);
});
