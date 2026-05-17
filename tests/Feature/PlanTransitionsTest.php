<?php

use App\Growth\Transitions\ActivatePlan;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\StatusTransition;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Apollo',
        'rigor_level' => 2,
    ]);

    $this->makePlan = fn (string $status): ProjectPlan => ProjectPlan::create([
        'project_id' => $this->project->id,
        'status' => $status,
    ]);

    $this->actingAs($this->user);
});

// ---- base action ----

it('rejects an illegal plan transition without writing a row', function () {
    $plan = ($this->makePlan)('draft');

    expect(fn () => (new ActivatePlan)->apply($plan))
        ->toThrow(IllegalTransitionException::class, 'Cannot activate a plan that is draft.');

    expect(StatusTransition::count())->toBe(0);
});
