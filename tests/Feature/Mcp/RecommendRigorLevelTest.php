<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Assurance\RecommendRigorLevel;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

function recommendRigor(Project $project): array
{
    $captured = [];
    ReadonlyServer::tool(RecommendRigorLevel::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$captured) {
            $captured = $json->toArray();
            $json->etc();
        });

    return $captured;
}

function adoptedProject(User $user, int $rigorLevel): Project
{
    return Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Adopted',
        'rigor_level' => $rigorLevel,
        'adopted_at' => now(),
    ]);
}

it('reports qualifies_for_next for an adopted L1 project with a milestone and a work item', function () {
    $project = adoptedProject($this->user, 1);
    Milestone::create(['project_id' => $project->id, 'name' => 'M1', 'status' => 'pending']);
    WorkItem::create(['project_id' => $project->id, 'kind' => 'task', 'name' => 'Build it']);

    $verdict = recommendRigor($project);

    expect($verdict['adopted'])->toBeTrue()
        ->and($verdict['at_ceiling'])->toBeFalse()
        ->and($verdict['current_level'])->toBe(1)
        ->and($verdict['next_level'])->toBe(2)
        ->and($verdict['qualifies_for_next'])->toBeTrue()
        ->and($verdict['blocking_findings'])->toBe([]);
});

it('reports does-not-qualify with the blocking errors when the L2 work-planning rules are unmet', function () {
    $project = adoptedProject($this->user, 1);

    $verdict = recommendRigor($project);

    expect($verdict['qualifies_for_next'])->toBeFalse()
        ->and($verdict['next_level'])->toBe(2);

    $blockingRules = collect($verdict['blocking_findings'])->pluck('rule');
    expect($blockingRules)->toContain('pmp.milestones.empty')
        ->and($blockingRules)->toContain('pmp.wbs.empty');
    expect(collect($verdict['blocking_findings'])->pluck('severity')->unique()->all())
        ->toBe(['error']);
});

it('advances exactly one rung — an L2 project is assessed against L3, never higher', function () {
    $project = adoptedProject($this->user, 2);

    $verdict = recommendRigor($project);

    expect($verdict['current_level'])->toBe(2)
        ->and($verdict['next_level'])->toBe(3);
});

it('returns an at-ceiling verdict for an adopted level-4 project', function () {
    $project = adoptedProject($this->user, 4);

    $verdict = recommendRigor($project);

    expect($verdict['adopted'])->toBeTrue()
        ->and($verdict['at_ceiling'])->toBeTrue()
        ->and($verdict['qualifies_for_next'])->toBeFalse()
        ->and($verdict['next_level'])->toBeNull();
});

it('returns a not-applicable verdict for a non-adopted project', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Greenfield',
        'rigor_level' => 1,
    ]);

    $verdict = recommendRigor($project);

    expect($verdict['adopted'])->toBeFalse()
        ->and($verdict['qualifies_for_next'])->toBeFalse()
        ->and($verdict['next_level'])->toBeNull();
});

it('never persists a rigor_level change, even on a qualifying verdict', function () {
    $project = adoptedProject($this->user, 1);
    Milestone::create(['project_id' => $project->id, 'name' => 'M1', 'status' => 'pending']);
    WorkItem::create(['project_id' => $project->id, 'kind' => 'task', 'name' => 'Build it']);

    $verdict = recommendRigor($project);

    expect($verdict['qualifies_for_next'])->toBeTrue()
        ->and($project->fresh()->rigor_level)->toBe(1);
});
