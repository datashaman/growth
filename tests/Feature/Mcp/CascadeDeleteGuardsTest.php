<?php

use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\ArchitectureServer;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\VerificationServer;
use App\Mcp\Tools\DeleteArchitectureView;
use App\Mcp\Tools\DeleteRelease;
use App\Mcp\Tools\DeleteVerificationPlan;
use App\Mcp\Tools\Test\DeleteTestPlan;
use App\Models\DesignView;
use App\Models\Project;
use App\Models\Release;
use App\Models\TestPlan;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $this->user = $user;
});

it('refuses to delete a release without matching confirm_name', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'p', 'rigor_level' => 1]);
    $release = Release::create(['project_id' => $project->id, 'version' => '1.0', 'name' => 'GA', 'status' => 'planned']);

    PlanningServer::tool(DeleteRelease::class, [
        'id' => $release->id,
        'confirm_name' => 'Not GA',
    ])->assertHasErrors(['Confirmation mismatch']);

    expect(Release::find($release->id))->not->toBeNull();

    PlanningServer::tool(DeleteRelease::class, [
        'id' => $release->id,
        'confirm_name' => 'GA',
    ])->assertOk()->assertSee('deleted');

    expect(Release::find($release->id))->toBeNull();
});

it('refuses to delete an architecture view without matching confirm_name', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'p', 'rigor_level' => 1]);
    $view = DesignView::create(['project_id' => $project->id, 'viewpoint' => 'logical', 'name' => 'Top-level']);

    ArchitectureServer::tool(DeleteArchitectureView::class, [
        'id' => $view->id,
        'confirm_name' => 'Wrong',
    ])->assertHasErrors(['Confirmation mismatch']);

    expect(DesignView::find($view->id))->not->toBeNull();

    ArchitectureServer::tool(DeleteArchitectureView::class, [
        'id' => $view->id,
        'confirm_name' => 'Top-level',
    ])->assertOk()->assertSee('deleted');

    expect(DesignView::find($view->id))->toBeNull();
});

it('refuses to delete a verification plan without matching confirm_name', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'p', 'rigor_level' => 1]);
    $plan = TestPlan::create(['project_id' => $project->id, 'level' => 'unit', 'name' => 'Unit Plan']);

    VerificationServer::tool(DeleteVerificationPlan::class, [
        'id' => $plan->id,
        'confirm_name' => 'Wrong',
    ])->assertHasErrors(['Confirmation mismatch']);

    expect(TestPlan::find($plan->id))->not->toBeNull();

    VerificationServer::tool(DeleteVerificationPlan::class, [
        'id' => $plan->id,
        'confirm_name' => 'Unit Plan',
    ])->assertOk()->assertSee('deleted');

    expect(TestPlan::find($plan->id))->toBeNull();
});

it('refuses to delete a test plan without matching confirm_name', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'p', 'rigor_level' => 1]);
    $plan = TestPlan::create(['project_id' => $project->id, 'level' => 'integration', 'name' => 'Integration Plan']);

    AllServer::tool(DeleteTestPlan::class, [
        'id' => $plan->id,
        'confirm_name' => 'Wrong',
    ])->assertHasErrors(['Confirmation mismatch']);

    expect(TestPlan::find($plan->id))->not->toBeNull();

    AllServer::tool(DeleteTestPlan::class, [
        'id' => $plan->id,
        'confirm_name' => 'Integration Plan',
    ])->assertOk()->assertSee('deleted');

    expect(TestPlan::find($plan->id))->toBeNull();
});
