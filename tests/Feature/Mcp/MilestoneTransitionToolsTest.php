<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\AchieveMilestone;
use App\Mcp\Tools\Plan\UpsertMilestone;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

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
});

it('achieves a pending milestone and records a transition', function () {
    $milestone = ($this->makeMilestone)('pending');
    $milestone->workItems()->attach(WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Ship it',
        'status' => 'done',
    ])->id);

    PlanningServer::tool(AchieveMilestone::class, ['milestone_id' => $milestone->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'pending')->where('to_status', 'achieved')->etc();
        });

    expect($milestone->fresh()->status)->toBe('achieved');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('achieved')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($transition->transitionable->is($milestone))->toBeTrue();
});

it('rejects achieving a milestone that is not pending', function () {
    $milestone = ($this->makeMilestone)('achieved');

    PlanningServer::tool(AchieveMilestone::class, ['milestone_id' => $milestone->id])
        ->assertHasErrors(['Cannot achieve a milestone that is achieved.']);

    expect(StatusTransition::count())->toBe(0);
});

it('rejects status passed to upsert-milestone with a pointer to the transition tool', function () {
    PlanningServer::tool(UpsertMilestone::class, [
        'project_id' => $this->project->id,
        'name' => 'No status here',
        'status' => 'achieved',
    ])
        ->assertHasErrors(['Milestone status is not set here. Use the milestone transition tool (achieve) to move status through validated transitions.']);

    expect(Milestone::where('name', 'No status here')->exists())->toBeFalse();
});
