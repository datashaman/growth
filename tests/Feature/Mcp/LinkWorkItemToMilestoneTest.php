<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\LinkWorkItemToMilestone;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Linky',
        'rigor_level' => 2,
    ]);

    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Build it',
    ]);

    $this->milestone = Milestone::create([
        'project_id' => $this->project->id,
        'name' => 'M1',
    ]);
});

it('links a work item to a milestone in the same project', function () {
    PlanningServer::tool(LinkWorkItemToMilestone::class, [
        'work_item_id' => $this->workItem->id,
        'milestone_id' => $this->milestone->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('attached', true)->etc();
        });

    expect($this->workItem->fresh()->milestones()->count())->toBe(1);
});

it('rejects linking a work item to a milestone in another project', function () {
    $otherProject = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Elsewhere',
        'rigor_level' => 2,
    ]);
    $otherMilestone = Milestone::create([
        'project_id' => $otherProject->id,
        'name' => 'Cross',
    ]);

    PlanningServer::tool(LinkWorkItemToMilestone::class, [
        'work_item_id' => $this->workItem->id,
        'milestone_id' => $otherMilestone->id,
    ])
        ->assertHasErrors(['A work item can only be linked to a milestone in the same project.']);

    expect($this->workItem->fresh()->milestones()->count())->toBe(0);
});
