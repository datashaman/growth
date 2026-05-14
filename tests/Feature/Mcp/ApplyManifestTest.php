<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Manifest\ApplyManifest;
use App\Models\Concern;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Stakeholder;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

function manifest(array $project = [], array $stakeholders = [], array $concerns = [], array $capabilities = []): array
{
    return [
        'project' => $project + ['name' => 'Imported', 'description' => 'desc', 'rigor_level' => 2, 'status' => 'active'],
        'stakeholders' => $stakeholders,
        'concerns' => $concerns,
        'capabilities' => $capabilities,
    ];
}

it('creates project, stakeholders, concerns and capabilities from scratch in fail mode', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            stakeholders: [
                ['slug' => 'pm', 'name' => 'Product Manager', 'role' => 'PM', 'kind' => 'individual'],
            ],
            concerns: [
                ['text' => 'Performance budgets must hold under load.', 'raised_by' => 'pm'],
            ],
            capabilities: [
                ['slug' => 'cap-greeting', 'text' => 'The app shall greet the user on first run.', 'type' => 'functional'],
            ],
        ),
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('project_id')
            ->where('mode', 'fail')
            ->where('effective_mode', 'fail')
            ->where('dry_run', false)
            ->where('counts.project_created', true)
            ->where('counts.stakeholders_created', 1)
            ->where('counts.concerns_created', 1)
            ->where('counts.capabilities_created', 1)
            ->has('slugs.capabilities.cap-greeting')
            ->etc();
    });

    expect(Project::where('name', 'Imported')->exists())->toBeTrue();
    $project = Project::where('name', 'Imported')->first();
    expect(Stakeholder::where('project_id', $project->id)->count())->toBe(1);
    expect(Concern::where('project_id', $project->id)->count())->toBe(1);
    expect(Requirement::where('project_id', $project->id)->where('slug', 'cap-greeting')->exists())->toBeTrue();
});

it('rolls back when dry_run is true', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            capabilities: [
                ['slug' => 'cap-x', 'text' => 'The thing.', 'type' => 'functional'],
            ],
        ),
        'dry_run' => true,
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('dry_run', true)
            ->where('counts.project_created', true)
            ->where('counts.capabilities_created', 1)
            ->etc();
    });

    expect(Project::where('name', 'Imported')->exists())->toBeFalse();
    expect(Requirement::where('slug', 'cap-x')->exists())->toBeFalse();
});

it('fail mode aborts when existing entity differs', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Existing', 'rigor_level' => 2]);
    Requirement::create([
        'project_id' => $project->id,
        'slug' => 'cap-a',
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Original text.',
    ]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Existing'],
            'capabilities' => [
                ['slug' => 'cap-a', 'text' => 'Different text.', 'type' => 'functional'],
            ],
        ],
    ]);

    $response->assertHasErrors(['fail mode aborts']);
    expect(Requirement::where('slug', 'cap-a')->first()->text)->toBe('Original text.');
});

it('merge mode updates existing entities by natural key', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Existing', 'rigor_level' => 2]);
    Requirement::create([
        'project_id' => $project->id,
        'slug' => 'cap-a',
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Original text.',
    ]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Existing'],
            'capabilities' => [
                ['slug' => 'cap-a', 'text' => 'Updated text.', 'type' => 'functional'],
            ],
        ],
        'mode' => 'merge',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.capabilities_updated', 1)
            ->where('counts.capabilities_created', 0)
            ->etc();
    });

    expect(Requirement::where('slug', 'cap-a')->first()->text)->toBe('Updated text.');
});

it('replace mode requires confirm matching project name', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Production', 'rigor_level' => 2]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Production'],
        ],
        'mode' => 'replace',
    ]);

    $response->assertHasErrors(['confirm']);
});

it('replace mode wipes existing child entities and recreates from manifest', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Wipe', 'rigor_level' => 2]);
    Requirement::create([
        'project_id' => $project->id,
        'slug' => 'old-cap',
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Old.',
    ]);
    Stakeholder::create(['project_id' => $project->id, 'name' => 'Old Person']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Wipe'],
            'capabilities' => [
                ['slug' => 'new-cap', 'text' => 'New.', 'type' => 'functional'],
            ],
        ],
        'mode' => 'replace',
        'confirm' => 'Wipe',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.capabilities_deleted', 1)
            ->where('counts.stakeholders_deleted', 1)
            ->where('counts.capabilities_created', 1)
            ->etc();
    });

    expect(Requirement::where('slug', 'old-cap')->exists())->toBeFalse();
    expect(Requirement::where('slug', 'new-cap')->exists())->toBeTrue();
    expect(Stakeholder::where('project_id', $project->id)->where('name', 'Old Person')->exists())->toBeFalse();
});

it('replace mode falls back to fail when project does not exist', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            capabilities: [['slug' => 'cap-a', 'text' => 'A capability.', 'type' => 'functional']],
        ),
        'mode' => 'replace',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('mode', 'replace')
            ->where('effective_mode', 'fail')
            ->where('counts.project_created', true)
            ->etc();
    });
});

it('resolves concern raised_by via stakeholder slug declared in the same manifest', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            stakeholders: [
                ['slug' => 'ops', 'name' => 'Ops Lead'],
            ],
            concerns: [
                ['text' => 'Pager fatigue.', 'raised_by' => 'ops'],
            ],
        ),
    ]);

    $response->assertOk();
    $stakeholder = Stakeholder::where('name', 'Ops Lead')->first();
    $concern = Concern::where('text', 'Pager fatigue.')->first();
    expect($concern->raised_by_stakeholder_id)->toBe($stakeholder->id);
});

it('reports drift when current updated_at is newer than manifest _exported_at', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Drifty', 'rigor_level' => 2]);
    Requirement::create([
        'project_id' => $project->id,
        'slug' => 'cap-a',
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Current.',
    ]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Drifty'],
            'capabilities' => [
                [
                    'slug' => 'cap-a',
                    'text' => 'Current.',
                    'type' => 'functional',
                    '_exported_at' => '2020-01-01T00:00:00Z',
                ],
            ],
        ],
        'mode' => 'merge',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('drift.0')
            ->where('drift.0.entity', 'capability')
            ->where('drift.0.identifier', 'cap-a')
            ->etc();
    });
});

it('rejects manifest with missing capability slug', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            capabilities: [['text' => 'No slug here.', 'type' => 'functional']],
        ),
    ]);

    $response->assertHasErrors(['slug']);
});

it('rejects manifest with stakeholder missing name', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            stakeholders: [['role' => 'PM']],
        ),
    ]);

    $response->assertHasErrors(['name']);
});

it('rejects applying to a project owned by another user', function () {
    $other = User::factory()->create();
    $foreign = Project::create(['user_id' => $other->id, 'name' => 'Foreign', 'rigor_level' => 2]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $foreign->id, 'name' => 'Foreign'],
        ],
    ]);

    $response->assertHasErrors();
    expect(Project::withoutGlobalScopes()->find($foreign->id)->name)->toBe('Foreign');
});
