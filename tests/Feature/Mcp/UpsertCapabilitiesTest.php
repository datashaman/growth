<?php

use App\Mcp\Servers\IntakeServer;
use App\Mcp\Tools\Capabilities\UpsertCapabilities;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Laravel\Passport\Passport;

it('upserts multiple capabilities in one batch and reports per-item failures without aborting', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create(['user_id' => $user->id, 'name' => 'Batchy', 'rigor_level' => 2]);

    $response = IntakeServer::tool(UpsertCapabilities::class, [
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
