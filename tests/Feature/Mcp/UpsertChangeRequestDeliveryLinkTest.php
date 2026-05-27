<?php

use App\Mcp\Servers\PlanningServer;
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

it('returns a structured conflict when an explicit update targets an existing change-request delivery link identity', function () {
    $existing = ChangeRequestDeliveryLink::create([
        'change_request_id' => $this->changeRequest->id,
        'type' => 'pull_request',
        'ref' => '#42',
        'url' => 'https://github.com/datashaman/growth/pull/42',
        'description' => 'canonical pull request',
    ]);
    $link = ChangeRequestDeliveryLink::create([
        'change_request_id' => $this->changeRequest->id,
        'type' => 'pull_request',
        'ref' => '#7',
        'url' => 'https://github.com/datashaman/growth/pull/7',
        'description' => 'stale pull request',
    ]);

    PlanningServer::tool(UpsertChangeRequestDeliveryLink::class, [
        'id' => $link->id,
        'change_request_id' => $this->changeRequest->id,
        'type' => 'pull_request',
        'ref' => 'PR-42',
        'url' => 'https://github.com/datashaman/growth/pull/42',
        'description' => 'manual repair with new notes',
    ])->assertOk()->assertStructuredContent(function ($json) use ($existing) {
        $json->where('id', $existing->id)
            ->where('ref', '#42')
            ->where('status', 'conflict')
            ->where('conflict', true)
            ->where('existing_id', $existing->id)
            ->etc();
    });

    expect($link->fresh()->ref)->toBe('#7')
        ->and(ChangeRequestDeliveryLink::where('change_request_id', $this->changeRequest->id)->count())->toBe(2);
});

it('treats an explicit update to an existing matching change-request delivery link identity as idempotent', function () {
    $existing = ChangeRequestDeliveryLink::create([
        'change_request_id' => $this->changeRequest->id,
        'type' => 'pull_request',
        'ref' => '#42',
        'url' => 'https://github.com/datashaman/growth/pull/42',
        'description' => 'canonical pull request',
    ]);
    $link = ChangeRequestDeliveryLink::create([
        'change_request_id' => $this->changeRequest->id,
        'type' => 'pull_request',
        'ref' => '#7',
    ]);

    PlanningServer::tool(UpsertChangeRequestDeliveryLink::class, [
        'id' => $link->id,
        'change_request_id' => $this->changeRequest->id,
        'type' => 'pull_request',
        'ref' => 'https://github.com/datashaman/growth/pull/42',
        'url' => 'https://github.com/datashaman/growth/pull/42',
        'description' => 'canonical pull request',
    ])->assertOk()->assertStructuredContent(function ($json) use ($existing) {
        $json->where('id', $existing->id)
            ->where('ref', '#42')
            ->where('status', 'idempotent')
            ->where('conflict', false)
            ->where('existing_id', null)
            ->etc();
    });

    expect($link->fresh()->ref)->toBe('#7')
        ->and(ChangeRequestDeliveryLink::where('change_request_id', $this->changeRequest->id)->count())->toBe(2);
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
