<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\ListProjectPlans;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\User;
use Laravel\Passport\Passport;

it('returns the plan for the given project and excludes plans from other projects', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'user_id' => $user->id,
        'name' => 'Plans',
        'rigor_level' => 2,
    ]);

    $other = Project::create([
        'user_id' => $user->id,
        'name' => 'Other',
        'rigor_level' => 2,
    ]);

    $plan = ProjectPlan::create([
        'project_id' => $project->id,
        'status' => 'active',
        'scope_summary' => 'mine',
    ]);
    ProjectPlan::create([
        'project_id' => $other->id,
        'status' => 'active',
        'scope_summary' => 'noise',
    ]);

    $response = PlanningServer::tool(ListProjectPlans::class, [
        'project_id' => $project->id,
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) use ($plan) {
        $json->where('total', 1)
            ->has('results', 1)
            ->where('results.0.id', $plan->id)
            ->where('results.0.status', 'active')
            ->etc();
    });
});

it('returns an empty result set when the project has no plan', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'user_id' => $user->id,
        'name' => 'Empty',
        'rigor_level' => 2,
    ]);

    $response = PlanningServer::tool(ListProjectPlans::class, [
        'project_id' => $project->id,
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('total', 0)
            ->has('results', 0)
            ->etc();
    });
});

it('filters by status', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'user_id' => $user->id,
        'name' => 'Plans',
        'rigor_level' => 2,
    ]);

    ProjectPlan::create([
        'project_id' => $project->id,
        'status' => 'draft',
        'scope_summary' => 'one',
    ]);

    $response = PlanningServer::tool(ListProjectPlans::class, [
        'project_id' => $project->id,
        'status' => 'active',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('total', 0)->etc();
    });
});

it('is registered on the planning server', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    $tools = $this->postJson('/mcp/planning', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    expect(collect($tools)->pluck('name')->all())->toContain('list-project-plans');
});
