<?php

use App\Mcp\Servers\VerificationServer;
use App\Mcp\Tools\Plan\UpsertCheckRun;
use App\Models\CheckRunEvidence;
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
        'name' => 'Checks',
        'rigor_level' => 2,
    ]);

    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Ship it',
    ]);

    $this->deliveryLink = WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => '#42',
    ]);
});

it('accepts a GitHub check_run payload from the sync action', function () {
    $args = [
        'work_item_delivery_link_id' => $this->deliveryLink->id,
        'provider' => 'github-actions',
        'name' => 'tests',
        'run_ref' => '555',
        'status' => 'completed',
        'conclusion' => 'success',
        'url' => 'https://github.com/datashaman/growth/runs/555',
        'started_at' => '2026-01-01T00:00:00Z',
        'completed_at' => '2026-01-01T00:05:00Z',
    ];

    VerificationServer::tool(UpsertCheckRun::class, $args)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('conclusion', 'success')
                ->where('status', 'completed')
                ->where('created', true)
                ->etc();
        });

    expect(CheckRunEvidence::where('work_item_delivery_link_id', $this->deliveryLink->id)->count())
        ->toBe(1);
});

it('upserts the same check across re-runs of a workflow', function () {
    $args = [
        'work_item_delivery_link_id' => $this->deliveryLink->id,
        'provider' => 'github-actions',
        'name' => 'tests',
        'status' => 'completed',
        'conclusion' => 'failure',
    ];

    VerificationServer::tool(UpsertCheckRun::class, $args)->assertOk();
    VerificationServer::tool(UpsertCheckRun::class, [...$args, 'conclusion' => 'success'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('conclusion', 'success')
                ->where('created', false)
                ->etc();
        });

    expect(CheckRunEvidence::where('work_item_delivery_link_id', $this->deliveryLink->id)->count())
        ->toBe(1);
});
