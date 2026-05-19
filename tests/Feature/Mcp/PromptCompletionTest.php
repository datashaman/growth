<?php

use App\Mcp\Prompts\CaptureIntent;
use App\Mcp\Prompts\StartProject;
use App\Mcp\Servers\IntakeServer;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

/**
 * Create a project in the acting user's active workspace.
 */
function completionProject(string $name): Project
{
    return Project::create([
        'workspace_id' => test()->user->active_workspace_id,
        'name' => $name,
        'rigor_level' => 2,
    ]);
}

it('advertises the completions capability on surface servers', function () {
    $capabilities = $this->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
            'capabilities' => [],
        ],
    ])->assertOk()->json('result.capabilities');

    expect($capabilities)->toHaveKey('completions');
});

it('completes the rigor_level argument of start-project', function () {
    IntakeServer::completion(StartProject::class, 'rigor_level', '')
        ->assertCompletionValues(['1', '2', '3', '4']);
});

it('filters rigor_level completions by the typed prefix', function () {
    IntakeServer::completion(StartProject::class, 'rigor_level', '3')
        ->assertCompletionValues(['3']);
});

it('returns no completions for a free-text start-project argument', function () {
    IntakeServer::completion(StartProject::class, 'name', '')
        ->assertCompletionCount(0);
});

it('completes project_id with the active workspace projects', function () {
    $alpha = completionProject('Alpha');
    $beta = completionProject('Beta');

    // The trait orders projects by name, so completion values are alphabetical.
    IntakeServer::completion(CaptureIntent::class, 'project_id', '')
        ->assertCompletionValues([$alpha->id, $beta->id]);
});

it('scopes project_id completions to the active workspace', function () {
    $mine = completionProject('Mine');

    Project::create([
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'Theirs',
        'rigor_level' => 2,
    ]);

    IntakeServer::completion(CaptureIntent::class, 'project_id', '')
        ->assertCompletionValues([$mine->id]);
});

it('returns no completions for a non-completable prompt argument', function () {
    completionProject('Alpha');

    IntakeServer::completion(CaptureIntent::class, 'unknown_argument', '')
        ->assertCompletionCount(0);
});
