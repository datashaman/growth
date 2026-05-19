<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Assurance\BuildEvidenceBundle;
use App\Mcp\Tools\Assurance\ScanContradictions;
use App\Mcp\Tools\Manifest\ApplyManifest;
use App\Mcp\Tools\Manifest\ExportManifest;
use App\Models\Project;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Notifications\ProgressNotification;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

function progressTestProject(): Project
{
    return Project::create([
        'workspace_id' => test()->user->active_workspace_id,
        'name' => 'Progress Project',
        'rigor_level' => 2,
        'status' => 'active',
    ]);
}

/**
 * Invoke a tool's generator handler to completion and return the decoded
 * `notifications/progress` messages the run emitted to the transport.
 *
 * @param  array<string,mixed>  $args
 * @return list<array<string,mixed>>
 */
function drainProgressTool(Tool $tool, array $args, ?string $token): array
{
    $transport = new FakeTransporter;
    $request = new Request($args, null, $token === null ? null : ['progressToken' => $token]);

    iterator_to_array($tool->handle($request, new ProgressNotification($transport)));

    return array_map(
        fn (string $json): array => json_decode($json, true),
        $transport->sentNotifications(),
    );
}

/**
 * Assert a captured progress stream is well-formed: one `notifications/progress`
 * message per phase, each tagged with the token and a strictly increasing
 * `progress` value against the expected `total`.
 *
 * @param  list<array<string,mixed>>  $progress
 */
function assertOrderedProgress(array $progress, int $expectedCount, string $token): void
{
    expect($progress)->toHaveCount($expectedCount);

    foreach ($progress as $index => $message) {
        expect($message['method'])->toBe('notifications/progress')
            ->and($message['params']['progressToken'])->toBe($token)
            ->and($message['params']['progress'])->toBe($index + 1)
            ->and($message['params']['total'])->toBe($expectedCount)
            ->and($message['params']['message'])->toBeString();
    }
}

it('streams a progress notification per scan phase for scan-contradictions', function () {
    $project = progressTestProject();
    $tool = app(ScanContradictions::class);

    assertOrderedProgress(
        drainProgressTool($tool, ['project_id' => $project->id], 'scan-token'),
        3,
        'scan-token',
    );

    expect(drainProgressTool($tool, ['project_id' => $project->id], null))->toBeEmpty();
});

it('streams a progress notification per readiness gate for build-evidence-bundle', function () {
    $project = progressTestProject();
    $tool = app(BuildEvidenceBundle::class);

    assertOrderedProgress(
        drainProgressTool($tool, ['project_id' => $project->id], 'evidence-token'),
        7,
        'evidence-token',
    );

    expect(drainProgressTool($tool, ['project_id' => $project->id], null))->toBeEmpty();
});

it('streams a progress notification per manifest section for export-manifest', function () {
    $project = progressTestProject();
    $tool = app(ExportManifest::class);

    assertOrderedProgress(
        drainProgressTool($tool, ['project_id' => $project->id], 'export-token'),
        7,
        'export-token',
    );

    expect(drainProgressTool($tool, ['project_id' => $project->id], null))->toBeEmpty();
});

it('streams a progress notification per manifest section for apply-manifest', function () {
    $tool = app(ApplyManifest::class);

    assertOrderedProgress(
        drainProgressTool($tool, ['manifest' => ['project' => ['name' => 'Apply With Progress']]], 'apply-token'),
        7,
        'apply-token',
    );

    expect(drainProgressTool($tool, ['manifest' => ['project' => ['name' => 'Apply No Progress']]], null))->toBeEmpty();
});

it('scan-contradictions still returns structured content as a streamed tool', function () {
    $project = progressTestProject();

    GovernanceServer::tool(ScanContradictions::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($project) {
            $json->where('project_id', $project->id)
                ->where('contradictions', 0)
                ->where('findings', [])
                ->etc();
        });
});

it('build-evidence-bundle still returns structured content as a streamed tool', function () {
    $project = progressTestProject();

    ReadonlyServer::tool(BuildEvidenceBundle::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($project) {
            $json->where('project_id', $project->id)
                ->where('project', 'Progress Project')
                ->has('readiness_status')
                ->has('gates')
                ->etc();
        });
});
