<?php

use App\Growth\Transitions\AchieveMilestone;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Milestones',
        'rigor_level' => 2,
    ]);

    $this->makeMilestone = fn (string $status): Milestone => Milestone::create([
        'project_id' => $this->project->id,
        'name' => 'Beta',
        'status' => $status,
    ]);

    $this->actingAs($this->user);
});

// ---- base action ----

it('rejects an illegal milestone transition without writing a row', function () {
    $milestone = ($this->makeMilestone)('achieved');

    expect(fn () => (new AchieveMilestone)->apply($milestone))
        ->toThrow(IllegalTransitionException::class, 'Cannot achieve a milestone that is achieved.');

    expect(StatusTransition::count())->toBe(0);
});
