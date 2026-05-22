<?php

/*
 * #381: the one-time dedupe command collapses pull-request delivery links that
 * differ only in ref form into a single canonical #<number> row, preserving
 * every linked check run, deployment, and evidence asset.
 */

use App\Models\CheckRunEvidence;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Dedupe',
        'rigor_level' => 2,
    ]);

    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Ship it',
    ]);
});

it('collapses duplicate pull-request rows into one canonical row, preserving all children', function () {
    $bare = WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => '14',
    ]);
    $hashed = WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => '#14',
    ]);

    $bare->checkRuns()->create(['provider' => 'github', 'name' => 'build', 'status' => 'completed']);
    $hashed->checkRuns()->create(['provider' => 'github', 'name' => 'lint', 'status' => 'completed']);

    $bare->evidenceAssets()->create(['path' => 'shots/bare.png', 'caption' => 'bare']);
    $hashed->evidenceAssets()->create(['path' => 'shots/hashed.png', 'caption' => 'hashed']);

    $deployment = Deployment::create([
        'project_id' => $this->project->id,
        'environment' => 'production',
        'status' => 'succeeded',
        'provider' => 'forge',
        'external_ref' => 'deploy-1',
    ]);
    $bare->deployments()->attach($deployment->id);

    $this->artisan('delivery-links:canonicalize-refs')->assertSuccessful();

    $survivors = WorkItemDeliveryLink::where('work_item_id', $this->workItem->id)
        ->where('type', 'pull_request')
        ->get();

    expect($survivors)->toHaveCount(1);

    $survivor = $survivors->first();
    expect($survivor->ref)->toBe('#14')
        ->and($survivor->checkRuns()->pluck('name')->sort()->values()->all())->toBe(['build', 'lint'])
        ->and($survivor->evidenceAssets()->pluck('path')->sort()->values()->all())->toBe(['shots/bare.png', 'shots/hashed.png'])
        ->and($survivor->deployments()->count())->toBe(1);
});

it('drops a duplicate check run that collides with the survivor on provider and name', function () {
    $survivor = WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => '#14',
    ]);
    $duplicate = WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => 'PR-14',
    ]);

    $survivor->checkRuns()->create(['provider' => 'github', 'name' => 'build', 'status' => 'completed', 'conclusion' => 'success']);
    $duplicate->checkRuns()->create(['provider' => 'github', 'name' => 'build', 'status' => 'completed', 'conclusion' => 'failure']);

    $this->artisan('delivery-links:canonicalize-refs')->assertSuccessful();

    expect(CheckRunEvidence::count())->toBe(1)
        ->and($survivor->fresh()->checkRuns()->first()->conclusion)->toBe('success');
});

it('rewrites a lone non-canonical ref to canonical form', function () {
    WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => 'PR-7',
    ]);

    $this->artisan('delivery-links:canonicalize-refs')->assertSuccessful();

    expect(WorkItemDeliveryLink::where('work_item_id', $this->workItem->id)->pluck('ref')->all())
        ->toBe(['#7']);
});

it('leaves branch and commit refs untouched', function () {
    WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'branch',
        'ref' => 'feature/14',
    ]);
    WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'commit',
        'ref' => 'a1b2c3d',
    ]);

    $this->artisan('delivery-links:canonicalize-refs')->assertSuccessful();

    expect(WorkItemDeliveryLink::where('type', 'branch')->value('ref'))->toBe('feature/14')
        ->and(WorkItemDeliveryLink::where('type', 'commit')->value('ref'))->toBe('a1b2c3d');
});
