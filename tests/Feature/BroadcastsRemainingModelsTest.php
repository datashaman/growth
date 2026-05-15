<?php

use App\Events\ProjectDataChanged;
use App\Models\Concern;
use App\Models\Deployment;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Release;
use App\Models\Role;
use App\Models\Stakeholder;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestPlan;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

$directProjectIdCases = [
    'Milestone' => fn (Project $p) => Milestone::create([
        'project_id' => $p->id, 'name' => 'PDR', 'status' => 'pending',
    ]),
    'Role' => fn (Project $p) => Role::create([
        'project_id' => $p->id, 'name' => 'Lead engineer',
    ]),
    'Stakeholder' => fn (Project $p) => Stakeholder::create([
        'project_id' => $p->id, 'name' => 'Mission control', 'kind' => 'individual',
    ]),
    'Concern' => fn (Project $p) => Concern::create([
        'project_id' => $p->id, 'text' => 'Thermal margins.',
    ]),
    'DesignView' => fn (Project $p) => DesignView::create([
        'project_id' => $p->id, 'viewpoint' => 'context', 'name' => 'Context view',
    ]),
    'TestPlan' => fn (Project $p) => TestPlan::create([
        'project_id' => $p->id, 'level' => 'unit', 'name' => 'Unit tests',
    ]),
    'Release' => fn (Project $p) => Release::create([
        'project_id' => $p->id, 'version' => '0.1.0', 'status' => 'planned',
    ]),
    'Deployment' => fn (Project $p) => Deployment::create([
        'project_id' => $p->id, 'environment' => 'staging', 'status' => 'planned',
    ]),
];

test('saving a {model} dispatches ProjectDataChanged on its project channel', function (Closure $factory) {
    Event::fake([ProjectDataChanged::class]);

    $factory($this->project);

    Event::assertDispatched(
        ProjectDataChanged::class,
        fn (ProjectDataChanged $e) => $e->projectId === (string) $this->project->id,
    );
})->with($directProjectIdCases);

test('saving a TestCase dispatches via plan relation', function () {
    Event::fake([ProjectDataChanged::class]);

    $plan = TestPlan::create([
        'project_id' => $this->project->id,
        'level' => 'unit',
        'name' => 'Unit tests',
    ]);

    TestCaseModel::create([
        'test_plan_id' => $plan->id,
        'name' => 'engine ignition',
        'expected_results' => 'thrust within tolerance',
    ]);

    Event::assertDispatched(
        ProjectDataChanged::class,
        fn (ProjectDataChanged $e) => $e->projectId === (string) $this->project->id,
    );
});
