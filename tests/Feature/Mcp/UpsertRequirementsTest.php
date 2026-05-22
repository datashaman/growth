<?php

use App\Mcp\Servers\IntakeServer;
use App\Mcp\Tools\Requirements\UpsertRequirements;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Laravel\Passport\Passport;

it('upserts multiple requirements in one batch and reports per-item failures without aborting', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create(['workspace_id' => $user->active_workspace_id, 'name' => 'Batchy', 'rigor_level' => 2]);

    $response = IntakeServer::tool(UpsertRequirements::class, [
        'items' => [
            [
                'project_id' => $project->id,
                'layer' => 'software',
                'type' => 'functional',
                'text' => 'The app shall greet the user on first run.',
                'priority' => 'medium',
            ],
            [
                'project_id' => $project->id,
                'layer' => 'software',
                'type' => 'functional',
                'text' => 'tiny',
            ],
            [
                'project_id' => $project->id,
                'layer' => 'software',
                'type' => 'functional',
                'text' => 'The app shall persist todos across reloads.',
                'acceptance_checks' => [
                    'Reloading the page restores the prior list.',
                ],
                'priority' => 'high',
            ],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('items', 3)
            ->where('items.0.ok', true)
            ->where('items.1.ok', false)
            ->has('items.1.errors.text')
            ->where('items.2.ok', true)
            ->etc();
    });

    expect(Requirement::where('project_id', $project->id)->count())->toBe(2);
});

it('returns the short per-document reference for each created requirement', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $project = Project::create(['workspace_id' => $user->active_workspace_id, 'name' => 'Refs', 'rigor_level' => 2]);

    IntakeServer::tool(UpsertRequirements::class, [
        'items' => [
            [
                'project_id' => $project->id,
                'layer' => 'software',
                'type' => 'functional',
                'text' => 'The app shall greet the user on first run.',
            ],
            [
                'project_id' => $project->id,
                'layer' => 'software',
                'type' => 'functional',
                'text' => 'The app shall persist todos across reloads.',
            ],
        ],
    ])->assertOk()->assertStructuredContent(function ($json) {
        $json->where('items.0.reference', 'SRS-001')
            ->where('items.1.reference', 'SRS-002')
            ->etc();
    });
});

it('creates a requirement flagged as UI-bearing and defaults the flag to false', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $project = Project::create(['workspace_id' => $user->active_workspace_id, 'name' => 'UI', 'rigor_level' => 2]);

    IntakeServer::tool(UpsertRequirements::class, [
        'items' => [
            [
                'project_id' => $project->id,
                'layer' => 'software',
                'type' => 'functional',
                'text' => 'The app shall render a dashboard chart.',
                'renders_ui' => true,
            ],
            [
                'project_id' => $project->id,
                'layer' => 'software',
                'type' => 'functional',
                'text' => 'The app shall compute totals nightly.',
            ],
        ],
    ])->assertOk();

    $ui = Requirement::where('text', 'The app shall render a dashboard chart.')->sole();
    $headless = Requirement::where('text', 'The app shall compute totals nightly.')->sole();

    expect($ui->renders_ui)->toBeTrue()
        ->and($headless->renders_ui)->toBeFalse();
});

it('leaves renders_ui untouched when an update omits the flag', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $project = Project::create(['workspace_id' => $user->active_workspace_id, 'name' => 'UI', 'rigor_level' => 2]);

    $requirement = Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The app shall render a dashboard chart.',
        'renders_ui' => true,
    ]);

    IntakeServer::tool(UpsertRequirements::class, [
        'items' => [
            [
                'id' => $requirement->id,
                'project_id' => $project->id,
                'layer' => 'software',
                'type' => 'functional',
                'text' => 'The app shall render a dashboard chart with a legend.',
            ],
        ],
    ])->assertOk();

    expect($requirement->fresh()->renders_ui)->toBeTrue();
});
