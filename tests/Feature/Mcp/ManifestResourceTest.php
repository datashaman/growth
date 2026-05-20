<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Manifest\ApplyManifest;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

function populatedManifestProjectId(): string
{
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => [
                'name' => 'Manifest Resource Project',
                'rigor_level' => 2,
                'status' => 'active',
            ],
            'stakeholders' => [
                ['slug' => 'pm', 'name' => 'Product Manager', 'role' => 'PM', 'kind' => 'individual'],
            ],
            'concerns' => [
                ['slug' => 'perf', 'text' => 'Performance budgets must hold.', 'raised_by' => 'pm'],
            ],
            'requirements' => [
                ['slug' => 'cap-a', 'text' => 'The app shall greet users.', 'type' => 'functional'],
            ],
            'plan' => [
                'status' => 'active',
                'scope_summary' => 'Single-page app.',
                'roles' => [['slug' => 'fe', 'name' => 'Frontend']],
                'milestones' => [['slug' => 'm1', 'name' => 'MVP', 'status' => 'pending']],
                'work_items' => [
                    ['slug' => 'wi-1', 'name' => 'Implement greeting', 'kind' => 'deliverable', 'status' => 'todo'],
                ],
            ],
        ],
    ]);
    $response->assertOk();
    $captured = null;
    $response->assertStructuredContent(function ($json) use (&$captured) {
        $captured = $json->toArray();
        $json->etc();
    });

    return $captured['project_id'];
}

it('serves a manifest TOC with per-section counts', function () {
    $projectId = populatedManifestProjectId();

    readResource(ReadonlyServer::class, "growth://projects/{$projectId}/manifest")
        ->assertOk()
        ->assertSee([
            $projectId,
            '"sections_available"',
            '"stakeholders":{"count":1}',
            '"requirements":{"count":1}',
            '"present":true',
            "growth://projects/{$projectId}/manifest/requirements",
        ]);
});

it('returns a not-found error for an unknown project on the TOC resource', function () {
    readResource(ReadonlyServer::class, 'growth://projects/01jzzzzzzzzzzzzzzzzzzzzzzz/manifest')
        ->assertHasErrors(['not found']);
});

it('serves a single manifest section', function () {
    $projectId = populatedManifestProjectId();

    readResource(ReadonlyServer::class, "growth://projects/{$projectId}/manifest/requirements")
        ->assertOk()
        ->assertSee([
            '"section":"requirements"',
            '"slug":"cap-a"',
            $projectId,
        ]);
});

it('errors for an unknown manifest section name', function () {
    $projectId = populatedManifestProjectId();

    readResource(ReadonlyServer::class, "growth://projects/{$projectId}/manifest/bogus")
        ->assertHasErrors(['Unknown manifest section']);
});

it('does not serve a manifest from another workspace', function () {
    $other = User::factory()->create();
    $otherProject = Project::withoutGlobalScopes()->create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Theirs',
        'rigor_level' => 2,
    ]);

    readResource(ReadonlyServer::class, "growth://projects/{$otherProject->id}/manifest")
        ->assertHasErrors(['not found']);
});
