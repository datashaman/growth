<?php

use App\Mcp\Servers\VerificationServer;
use App\Mcp\Tools\Verification\LinkVerificationRunEvidence;
use App\Mcp\Tools\Verification\LogVerificationRun;
use App\Models\EvidenceAsset;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRun;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Laravel\Passport\Passport;

function evidenceAssetIn(Project $project, string $caption = 'shot.png'): EvidenceAsset
{
    $workItem = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Build the UI',
    ]);
    $link = WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'evidence',
        'ref' => '#1',
    ]);

    return EvidenceAsset::create([
        'work_item_delivery_link_id' => $link->id,
        'path' => 'docs/evidence/'.$caption,
        'caption' => $caption,
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Verif evidence',
        'rigor_level' => 3,
    ]);
    $this->plan = TestPlan::create([
        'project_id' => $this->project->id,
        'level' => 'unit',
        'name' => 'Unit plan',
    ]);
    $this->case = TestCase::create([
        'test_plan_id' => $this->plan->id,
        'name' => 'Chart renders',
        'expected_results' => 'the chart is visible',
    ]);
});

it('logs a verification run with cited evidence assets', function () {
    $asset = evidenceAssetIn($this->project);

    VerificationServer::tool(LogVerificationRun::class, [
        'test_case_id' => $this->case->id,
        'status' => 'pass',
        'evidence_asset_ids' => [$asset->id],
    ])->assertOk()->assertStructuredContent(function ($json) {
        $json->where('evidence_asset_count', 1)->etc();
    });

    expect(TestRun::sole()->evidenceAssets()->pluck('evidence_assets.id')->all())
        ->toBe([$asset->id]);
});

it('logs a verification run with no evidence when none is cited', function () {
    VerificationServer::tool(LogVerificationRun::class, [
        'test_case_id' => $this->case->id,
        'status' => 'pass',
    ])->assertOk();

    expect(TestRun::sole()->evidenceAssets()->count())->toBe(0);
});

it('rejects a foreign evidence asset when logging a run', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);
    $foreignAsset = evidenceAssetIn($strangerProject);

    VerificationServer::tool(LogVerificationRun::class, [
        'test_case_id' => $this->case->id,
        'status' => 'pass',
        'evidence_asset_ids' => [$foreignAsset->id],
    ])->assertHasErrors();
});

it('attaches evidence to an existing run and is idempotent', function () {
    $run = TestRun::create([
        'test_case_id' => $this->case->id,
        'status' => 'pass',
        'run_at' => now(),
    ]);
    $asset = evidenceAssetIn($this->project);

    VerificationServer::tool(LinkVerificationRunEvidence::class, [
        'test_run_id' => $run->id,
        'evidence_asset_ids' => [$asset->id],
    ])->assertOk()->assertStructuredContent(function ($json) {
        $json->where('attached', 1)->where('unchanged', 0)->etc();
    });

    VerificationServer::tool(LinkVerificationRunEvidence::class, [
        'test_run_id' => $run->id,
        'evidence_asset_ids' => [$asset->id],
    ])->assertOk()->assertStructuredContent(function ($json) {
        $json->where('attached', 0)->where('unchanged', 1)->etc();
    });

    expect($run->evidenceAssets()->count())->toBe(1);
});

it('rejects a foreign evidence asset when attaching to an existing run', function () {
    $run = TestRun::create([
        'test_case_id' => $this->case->id,
        'status' => 'pass',
        'run_at' => now(),
    ]);
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);
    $foreignAsset = evidenceAssetIn($strangerProject);

    VerificationServer::tool(LinkVerificationRunEvidence::class, [
        'test_run_id' => $run->id,
        'evidence_asset_ids' => [$foreignAsset->id],
    ])->assertHasErrors();
});
