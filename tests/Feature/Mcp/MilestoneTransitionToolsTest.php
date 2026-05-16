<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\DeferMilestone;
use App\Mcp\Tools\Plan\HitMilestone;
use App\Mcp\Tools\Plan\MissMilestone;
use App\Mcp\Tools\Plan\UpsertMilestone;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
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
        'target_date' => '2026-06-01',
        'status' => $status,
    ]);
});

it('hits a pending milestone and records a transition', function () {
    $milestone = ($this->makeMilestone)('pending');

    PlanningServer::tool(HitMilestone::class, ['milestone_id' => $milestone->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'pending')->where('to_status', 'hit')->etc();
        });

    expect($milestone->fresh()->status)->toBe('hit');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('hit')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($transition->transitionable->is($milestone))->toBeTrue();
});

it('rejects hitting a milestone that is not pending', function () {
    $milestone = ($this->makeMilestone)('missed');

    PlanningServer::tool(HitMilestone::class, ['milestone_id' => $milestone->id])
        ->assertHasErrors(['Cannot hit a milestone that is missed.']);

    expect(StatusTransition::count())->toBe(0);
});

it('misses a pending milestone', function () {
    $milestone = ($this->makeMilestone)('pending');

    PlanningServer::tool(MissMilestone::class, ['milestone_id' => $milestone->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('to_status', 'missed')->etc();
        });

    expect($milestone->fresh()->status)->toBe('missed');
});

it('rejects missing a milestone that is already hit', function () {
    $milestone = ($this->makeMilestone)('hit');

    PlanningServer::tool(MissMilestone::class, ['milestone_id' => $milestone->id])
        ->assertHasErrors(['Cannot miss a milestone that is hit.']);
});

it('defers a pending milestone and applies the new target date', function () {
    $milestone = ($this->makeMilestone)('pending');

    PlanningServer::tool(DeferMilestone::class, [
        'milestone_id' => $milestone->id,
        'new_target_date' => '2026-09-30',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('to_status', 'deferred')
                ->where('new_target_date', '2026-09-30')
                ->etc();
        });

    $fresh = $milestone->fresh();
    expect($fresh->status)->toBe('deferred')
        ->and($fresh->target_date->format('Y-m-d'))->toBe('2026-09-30');
});

it('requires a new target date to defer a milestone', function () {
    $milestone = ($this->makeMilestone)('pending');

    PlanningServer::tool(DeferMilestone::class, ['milestone_id' => $milestone->id])
        ->assertHasErrors();

    expect($milestone->fresh()->status)->toBe('pending');
});

it('rejects deferring a milestone that is not pending', function () {
    $milestone = ($this->makeMilestone)('deferred');

    PlanningServer::tool(DeferMilestone::class, [
        'milestone_id' => $milestone->id,
        'new_target_date' => '2026-09-30',
    ])->assertHasErrors(['Cannot defer a milestone that is deferred.']);
});

it('rejects status passed to upsert-milestone with a pointer to the transition tools', function () {
    PlanningServer::tool(UpsertMilestone::class, [
        'project_id' => $this->project->id,
        'name' => 'No status here',
        'status' => 'hit',
    ])
        ->assertHasErrors(['Milestone status is not set here. Use the milestone transition tools (hit, miss, defer) to move status through validated transitions.']);

    expect(Milestone::where('name', 'No status here')->exists())->toBeFalse();
});
