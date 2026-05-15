<?php

use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\ProjectPlanBaseline;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

it('shows the empty-state message when no baselines exist', function () {
    $this->actingAs($this->user)
        ->get('/plan?project='.$this->project->id)
        ->assertOk()
        ->assertSee('Baselines')
        ->assertSee('No baselines captured');
});

it('lists baselines newest version first with author and note', function () {
    $plan = ProjectPlan::create([
        'project_id' => $this->project->id,
        'name' => 'v1',
        'status' => 'draft',
    ]);

    ProjectPlanBaseline::create([
        'project_plan_id' => $plan->id,
        'version' => 1,
        'snapshot' => ['hello' => 'world'],
        'baselined_at' => now()->subDays(2),
        'baselined_by_user_id' => $this->user->id,
        'note' => 'Initial cut',
    ]);
    ProjectPlanBaseline::create([
        'project_plan_id' => $plan->id,
        'version' => 2,
        'snapshot' => ['hello' => 'world'],
        'baselined_at' => now(),
        'baselined_by_user_id' => $this->user->id,
        'note' => 'Second snapshot',
    ]);

    $response = $this->actingAs($this->user)
        ->get('/plan?project='.$this->project->id)
        ->assertOk()
        ->assertSee('v2')
        ->assertSee('v1')
        ->assertSee('Initial cut')
        ->assertSee('Second snapshot')
        ->assertSee($this->user->name);

    $body = $response->getContent();
    expect(strpos($body, 'Second snapshot'))->toBeLessThan(strpos($body, 'Initial cut'));
});
