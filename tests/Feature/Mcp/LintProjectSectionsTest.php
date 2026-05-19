<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Lint\LintProject;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

it('returns all eight sections by default', function () {
    $project = Project::create(['workspace_id' => $this->user->active_workspace_id, 'name' => 'p', 'rigor_level' => 1]);

    $captured = null;
    ReadonlyServer::tool(LintProject::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$captured) {
            $captured = $json->toArray();
            $json->etc();
        });

    expect(array_keys($captured['sections']))
        ->toBe(['baselines', 'changes', 'requirements', 'architecture', 'verification', 'planning', 'reviews', 'adoption']);
});

it('filters to the requested sections when sections is provided', function () {
    $project = Project::create(['workspace_id' => $this->user->active_workspace_id, 'name' => 'p', 'rigor_level' => 1]);

    $captured = null;
    ReadonlyServer::tool(LintProject::class, [
        'project_id' => $project->id,
        'sections' => ['architecture', 'baselines'],
    ])->assertOk()->assertStructuredContent(function ($json) use (&$captured) {
        $captured = $json->toArray();
        $json->etc();
    });

    expect(array_keys($captured['sections']))
        ->toBe(['architecture', 'baselines']);
});

it('rejects unknown section names', function () {
    $project = Project::create(['workspace_id' => $this->user->active_workspace_id, 'name' => 'p', 'rigor_level' => 1]);

    ReadonlyServer::tool(LintProject::class, [
        'project_id' => $project->id,
        'sections' => ['bogus'],
    ])->assertHasErrors();
});

it('counts errors and warnings only over the returned sections', function () {
    $project = Project::create(['workspace_id' => $this->user->active_workspace_id, 'name' => 'p', 'rigor_level' => 1]);

    $full = ReadonlyServer::tool(LintProject::class, ['project_id' => $project->id])
        ->assertOk();
    $filtered = ReadonlyServer::tool(LintProject::class, [
        'project_id' => $project->id,
        'sections' => ['baselines'],
    ])->assertOk();

    $fullCounts = null;
    $filteredCounts = null;
    $full->assertStructuredContent(function ($json) use (&$fullCounts) {
        $fullCounts = $json->toArray();
        $json->etc();
    });
    $filtered->assertStructuredContent(function ($json) use (&$filteredCounts) {
        $filteredCounts = $json->toArray();
        $json->etc();
    });

    $fullTotal = $fullCounts['errors'] + $fullCounts['warnings'];
    $filteredTotal = $filteredCounts['errors'] + $filteredCounts['warnings'];

    expect($filteredTotal)->toBeLessThanOrEqual($fullTotal);
});

it('returns an empty adoption section for a non-adopted project', function () {
    $project = Project::create(['workspace_id' => $this->user->active_workspace_id, 'name' => 'p', 'rigor_level' => 1]);
    Requirement::create(['project_id' => $project->id, 'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall do a thing.']);

    $captured = null;
    ReadonlyServer::tool(LintProject::class, ['project_id' => $project->id, 'sections' => ['adoption']])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$captured) {
            $captured = $json->toArray();
            $json->etc();
        });

    expect($captured['sections']['adoption'])->toBe([])
        ->and($captured['informational'])->toBe(0);
});

it('reports adoption coverage gaps for an adopted project as informational findings', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'p',
        'rigor_level' => 1,
        'adopted_at' => now(),
    ]);
    Requirement::create(['project_id' => $project->id, 'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall do a thing.']);

    $captured = null;
    ReadonlyServer::tool(LintProject::class, ['project_id' => $project->id, 'sections' => ['adoption']])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$captured) {
            $captured = $json->toArray();
            $json->etc();
        });

    $adoption = $captured['sections']['adoption'];

    expect($adoption)->not->toBeEmpty()
        ->and(collect($adoption)->pluck('severity')->unique()->all())->toBe(['informational'])
        ->and(collect($adoption)->pluck('rule')->all())
        ->toContain('adoption.requirement.no_work_item', 'adoption.requirement.no_verification', 'adoption.project.no_architecture')
        ->and($captured['informational'])->toBe(count($adoption));
});

it('counts adoption findings as informational, not warnings', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'p',
        'rigor_level' => 1,
        'adopted_at' => now(),
    ]);

    $withAdoption = null;
    $withoutAdoption = null;
    ReadonlyServer::tool(LintProject::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$withAdoption) {
            $withAdoption = $json->toArray();
            $json->etc();
        });
    ReadonlyServer::tool(LintProject::class, [
        'project_id' => $project->id,
        'sections' => ['baselines', 'changes', 'requirements', 'architecture', 'verification', 'planning', 'reviews'],
    ])->assertOk()->assertStructuredContent(function ($json) use (&$withoutAdoption) {
        $withoutAdoption = $json->toArray();
        $json->etc();
    });

    expect($withAdoption['warnings'])->toBe($withoutAdoption['warnings'])
        ->and($withAdoption['errors'])->toBe($withoutAdoption['errors'])
        ->and($withAdoption['informational'])->toBeGreaterThan(0)
        ->and($withoutAdoption['informational'])->toBe(0);
});

it('counts a ui_no_mockup planning finding under informational, not warnings', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'p',
        'rigor_level' => 1,
    ]);
    $requirement = Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The dashboard shall render a chart.',
        'renders_ui' => true,
    ]);
    $workItem = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Build the dashboard',
        'needs_mockups' => false,
    ]);
    $requirement->workItems()->attach($workItem);

    $captured = null;
    ReadonlyServer::tool(LintProject::class, ['project_id' => $project->id, 'sections' => ['planning']])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$captured) {
            $captured = $json->toArray();
            $json->etc();
        });

    $planning = collect($captured['sections']['planning']);
    $finding = $planning->firstWhere('rule', 'pmp.requirement.ui_no_mockup');

    expect($finding)->not->toBeNull()
        ->and($finding['severity'])->toBe('informational')
        ->and($captured['informational'])->toBeGreaterThanOrEqual(1)
        // The informational finding must not leak into the error/warning tallies.
        ->and($captured['warnings'])->toBe($planning->where('severity', 'warning')->count())
        ->and($captured['errors'])->toBe($planning->where('severity', 'error')->count());
});
