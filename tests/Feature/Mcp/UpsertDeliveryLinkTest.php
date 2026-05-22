<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\UpsertDeliveryLink;
use App\Models\Project;
use App\Models\UnattributedGithubEvent;
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

it('accepts a visual-evidence gallery link from the sync action', function () {
    $args = [
        'work_item_id' => $this->workItem->id,
        'type' => 'evidence',
        'ref' => '#42',
        'url' => 'https://github.com/datashaman/growth/pull/42#issuecomment-1',
        'description' => 'Visual evidence: 8 screenshots',
    ];

    PlanningServer::tool(UpsertDeliveryLink::class, $args)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('type', 'evidence')
                ->where('ref', '#42')
                ->where('created', true)
                ->etc();
        });

    expect(WorkItemDeliveryLink::where('work_item_id', $this->workItem->id)->where('type', 'evidence')->count())
        ->toBe(1);
});

it('resolves every pull-request ref form to one canonical row', function (string $ref) {
    PlanningServer::tool(UpsertDeliveryLink::class, [
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => $ref,
    ])->assertOk()->assertStructuredContent(function ($json) {
        $json->where('ref', '#14')->etc();
    });

    expect(WorkItemDeliveryLink::where('work_item_id', $this->workItem->id)->where('ref', '#14')->count())
        ->toBe(1);
})->with(['14', '#14', 'PR-14', 'https://github.com/datashaman/growth/pull/14']);

it('treats the ref variants as the same row, so re-attachment is idempotent', function () {
    foreach (['14', '#14', 'PR-14', 'https://github.com/datashaman/growth/pull/14'] as $i => $ref) {
        PlanningServer::tool(UpsertDeliveryLink::class, [
            'work_item_id' => $this->workItem->id,
            'type' => 'pull_request',
            'ref' => $ref,
        ])->assertOk()->assertStructuredContent(function ($json) use ($i) {
            $json->where('created', $i === 0)->etc();
        });
    }

    expect(WorkItemDeliveryLink::where('work_item_id', $this->workItem->id)->where('type', 'pull_request')->count())
        ->toBe(1);
});

it('canonicalises the ref on the explicit-id update path too', function () {
    $link = WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => '#14',
    ]);

    PlanningServer::tool(UpsertDeliveryLink::class, [
        'id' => $link->id,
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => '14',
    ])->assertOk()->assertStructuredContent(function ($json) {
        $json->where('ref', '#14')->etc();
    });

    expect($link->fresh()->ref)->toBe('#14');
});

it('canonicalises an evidence ref that is a pull-request ref', function () {
    PlanningServer::tool(UpsertDeliveryLink::class, [
        'work_item_id' => $this->workItem->id,
        'type' => 'evidence',
        'ref' => 'PR-14',
    ])->assertOk()->assertStructuredContent(function ($json) {
        $json->where('ref', '#14')->etc();
    });
});

it('stores branch and commit refs exactly as supplied', function (string $type, string $ref) {
    PlanningServer::tool(UpsertDeliveryLink::class, [
        'work_item_id' => $this->workItem->id,
        'type' => $type,
        'ref' => $ref,
    ])->assertOk()->assertStructuredContent(function ($json) use ($ref) {
        $json->where('ref', $ref)->etc();
    });
})->with([
    ['branch', 'feature/14'],
    ['commit', 'a1b2c3d'],
]);

it('clears unattributed events for a branch once it is bound', function () {
    $this->project->update(['github_repo' => 'datashaman/growth']);

    foreach (['feature/bound', 'feature/other'] as $branch) {
        UnattributedGithubEvent::create([
            'github_repo' => 'datashaman/growth',
            'event_type' => 'check_run',
            'branch' => $branch,
            'commit_sha' => 'sha-'.$branch,
            'reason' => 'missing_link',
            'received_at' => now(),
        ]);
    }

    PlanningServer::tool(UpsertDeliveryLink::class, [
        'work_item_id' => $this->workItem->id,
        'type' => 'branch',
        'ref' => 'feature/bound',
    ])->assertOk();

    expect(UnattributedGithubEvent::where('branch', 'feature/bound')->exists())->toBeFalse();
    expect(UnattributedGithubEvent::where('branch', 'feature/other')->exists())->toBeTrue();
});

it('keeps unattributed events when a branch is bound to more than one work item', function () {
    $this->project->update(['github_repo' => 'datashaman/growth']);

    $secondItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Also ship it',
    ]);

    // An existing link already points feature/contested at one work item.
    WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'branch',
        'ref' => 'feature/contested',
    ]);

    UnattributedGithubEvent::create([
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'branch' => 'feature/contested',
        'commit_sha' => 'sha-contested',
        'reason' => 'missing_link',
        'received_at' => now(),
    ]);

    // Binding a second work item leaves the branch ambiguous, so the
    // exception stays — resolving it does not pick a single work item.
    PlanningServer::tool(UpsertDeliveryLink::class, [
        'work_item_id' => $secondItem->id,
        'type' => 'branch',
        'ref' => 'feature/contested',
    ])->assertOk();

    expect(UnattributedGithubEvent::where('branch', 'feature/contested')->exists())->toBeTrue();
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
