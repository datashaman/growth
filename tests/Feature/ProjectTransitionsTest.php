<?php

use App\Growth\Transitions\ActivateProject;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->makeProject = fn (string $status): Project => Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Apollo',
        'rigor_level' => 2,
        'status' => $status,
    ]);

    $this->actingAs($this->user);
});

// ---- base action ----

it('rejects an illegal project transition without writing a row', function () {
    $project = ($this->makeProject)('active');

    expect(fn () => (new ActivateProject)->apply($project))
        ->toThrow(IllegalTransitionException::class, 'Cannot activate a project that is active.');

    expect(StatusTransition::count())->toBe(0);
});
