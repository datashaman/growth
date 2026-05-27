<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Changes\DeleteChangeRequestDeliveryLink;
use App\Mcp\Tools\Changes\ListChangeRequestDeliveryLinks;
use App\Mcp\Tools\Changes\UpsertChangeRequestDeliveryLink;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestDeliveryLink;
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
        'name' => 'Growth',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/growth',
    ]);

    $this->changeRequest = ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Adjust telemetry scope',
        'category' => 'scope',
    ]);
});

it('accepts a GitHub pull request payload from the sync action', function () {
    $args = [
        'change_request_id' => $this->changeRequest->id,
        'type' => 'pull_request',
        'ref' => '#42',
        'url' => 'https://github.com/datashaman/growth/pull/42',
        'description' => 'GitHub pull request: Add widget',
    ];

    PlanningServer::tool(UpsertChangeRequestDeliveryLink::class, $args)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('change_request_id', $this->changeRequest->id)
                ->where('type', 'pull_request')
                ->where('ref', '#42')
                ->where('created', true)
                ->etc();
        });

    expect(ChangeRequestDeliveryLink::where('change_request_id', $this->changeRequest->id)->where('ref', '#42')->count())
        ->toBe(1);
});

it('canonicalises pull-request refs and upserts one row', function () {
    foreach (['42', '#42', 'PR-42', 'https://github.com/datashaman/growth/pull/42'] as $i => $ref) {
        PlanningServer::tool(UpsertChangeRequestDeliveryLink::class, [
            'change_request_id' => $this->changeRequest->id,
            'type' => 'pull_request',
            'ref' => $ref,
        ])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('ref', '#42')
                ->where('created', $i === 0)
                ->etc());
    }

    expect(ChangeRequestDeliveryLink::where('change_request_id', $this->changeRequest->id)->where('type', 'pull_request')->count())
        ->toBe(1);
});

it('stores branch and commit refs exactly as supplied', function (string $type, string $ref) {
    PlanningServer::tool(UpsertChangeRequestDeliveryLink::class, [
        'change_request_id' => $this->changeRequest->id,
        'type' => $type,
        'ref' => $ref,
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('ref', $ref)->etc());
})->with([
    ['branch', 'feature/telemetry'],
    ['commit', 'a1b2c3d'],
]);

it('clears unattributed events for a branch once it is bound to one change request', function () {
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

    PlanningServer::tool(UpsertChangeRequestDeliveryLink::class, [
        'change_request_id' => $this->changeRequest->id,
        'type' => 'branch',
        'ref' => 'feature/bound',
    ])->assertOk();

    expect(UnattributedGithubEvent::where('branch', 'feature/bound')->exists())->toBeFalse();
    expect(UnattributedGithubEvent::where('branch', 'feature/other')->exists())->toBeTrue();
});

it('keeps unattributed events when the branch is still ambiguous for work items', function () {
    $firstItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'First item',
    ]);
    $secondItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Second item',
    ]);

    foreach ([$firstItem, $secondItem] as $item) {
        WorkItemDeliveryLink::create([
            'work_item_id' => $item->id,
            'type' => 'branch',
            'ref' => 'feature/contested',
        ]);
    }

    UnattributedGithubEvent::create([
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'branch' => 'feature/contested',
        'commit_sha' => 'sha-contested',
        'reason' => 'ambiguous_branch',
        'received_at' => now(),
    ]);

    PlanningServer::tool(UpsertChangeRequestDeliveryLink::class, [
        'change_request_id' => $this->changeRequest->id,
        'type' => 'branch',
        'ref' => 'feature/contested',
    ])->assertOk();

    expect(UnattributedGithubEvent::where('branch', 'feature/contested')->exists())->toBeTrue();
});

it('deletes a change request delivery link by id', function () {
    $link = ChangeRequestDeliveryLink::create([
        'change_request_id' => $this->changeRequest->id,
        'type' => 'pull_request',
        'ref' => '#42',
        'url' => 'https://github.com/datashaman/growth/pull/42',
    ]);

    PlanningServer::tool(DeleteChangeRequestDeliveryLink::class, [
        'id' => $link->id,
    ])->assertOk()->assertStructuredContent(function ($json) use ($link) {
        $json->where('id', $link->id)
            ->where('change_request_id', $this->changeRequest->id)
            ->where('type', 'pull_request')
            ->where('ref', '#42')
            ->where('deleted', true);
    });

    expect(ChangeRequestDeliveryLink::whereKey($link->id)->exists())->toBeFalse();
});

it('does not delete a work item delivery link with the same ref', function () {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Implement telemetry',
    ]);
    $workItemLink = WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'pull_request',
        'ref' => '#42',
    ]);
    $changeRequestLink = ChangeRequestDeliveryLink::create([
        'change_request_id' => $this->changeRequest->id,
        'type' => 'pull_request',
        'ref' => '#42',
    ]);

    PlanningServer::tool(DeleteChangeRequestDeliveryLink::class, [
        'id' => $changeRequestLink->id,
    ])->assertOk();

    expect(ChangeRequestDeliveryLink::whereKey($changeRequestLink->id)->exists())->toBeFalse()
        ->and(WorkItemDeliveryLink::whereKey($workItemLink->id)->exists())->toBeTrue();
});

it('rejects deleting a change request delivery link from another workspace', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $otherUser->active_workspace_id,
        'name' => 'Other Growth',
        'rigor_level' => 2,
    ]);
    $otherChangeRequest = ChangeRequest::create([
        'project_id' => $otherProject->id,
        'title' => 'Other change',
        'category' => 'scope',
    ]);
    $foreignLink = ChangeRequestDeliveryLink::create([
        'change_request_id' => $otherChangeRequest->id,
        'type' => 'branch',
        'ref' => 'feature/foreign',
    ]);

    PlanningServer::tool(DeleteChangeRequestDeliveryLink::class, [
        'id' => $foreignLink->id,
    ])->assertHasErrors();

    expect(ChangeRequestDeliveryLink::withoutGlobalScopes()->whereKey($foreignLink->id)->exists())->toBeTrue();
});

it('exposes the delete change request delivery link tool on write surfaces', function () {
    foreach (['/mcp/planning', '/mcp/governance'] as $endpoint) {
        $tools = $this->postJson($endpoint, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => ['per_page' => 300],
        ])->assertOk()->json('result.tools');

        expect(collect($tools)->pluck('name')->all())->toContain('delete-change-request-delivery-link');
    }
});

it('lists change request delivery links by change request', function () {
    ChangeRequestDeliveryLink::create([
        'change_request_id' => $this->changeRequest->id,
        'type' => 'branch',
        'ref' => 'feature/telemetry',
        'description' => 'implementation branch',
    ]);

    PlanningServer::tool(ListChangeRequestDeliveryLinks::class, [
        'change_request_id' => $this->changeRequest->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('results.0.change_request_id', $this->changeRequest->id)
                ->where('results.0.change_request', 'Adjust telemetry scope')
                ->where('results.0.type', 'branch')
                ->where('results.0.ref', 'feature/telemetry')
                ->etc();
        });
});
