<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Lint\LintProject;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

it('returns all seven sections by default', function () {
    $project = Project::create(['workspace_id' => $this->user->active_workspace_id, 'name' => 'p', 'rigor_level' => 1]);

    $captured = null;
    ReadonlyServer::tool(LintProject::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$captured) {
            $captured = $json->toArray();
            $json->etc();
        });

    expect(array_keys($captured['sections']))
        ->toBe(['baselines', 'changes', 'requirements', 'architecture', 'verification', 'planning', 'reviews']);
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
