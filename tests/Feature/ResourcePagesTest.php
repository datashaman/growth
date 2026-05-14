<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'description' => 'Mission control.',
        'rigor_level' => 2,
    ]);
});

test('intent page renders stakeholders and concerns for the selected project', function () {
    $stakeholder = $this->project->stakeholders()->create([
        'name' => 'Mission Director',
        'role' => 'sponsor',
        'kind' => 'individual',
    ]);
    $this->project->concerns()->create([
        'raised_by_stakeholder_id' => $stakeholder->id,
        'text' => 'Re-entry heating margins',
    ]);

    $this->actingAs($this->user)
        ->get('/intent?project='.$this->project->id)
        ->assertOk()
        ->assertSee('Intent')
        ->assertSee('Mission Director')
        ->assertSee('Re-entry heating margins');
});

test('capabilities page renders requirements for the selected project', function () {
    $this->project->requirements()->create([
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'System shall ignite descent engine at T-10s.',
        'priority' => 'high',
    ]);

    $this->actingAs($this->user)
        ->get('/capabilities?project='.$this->project->id)
        ->assertOk()
        ->assertSee('Capabilities')
        ->assertSee('ignite descent engine')
        ->assertSee('SRS');
});

test('architecture page renders design views and elements', function () {
    $view = $this->project->designViews()->create([
        'viewpoint' => 'logical',
        'name' => 'Descent stack',
        'description' => 'Modules involved in landing.',
    ]);
    $view->elements()->create([
        'kind' => 'entity',
        'name' => 'GuidanceComputer',
        'type' => 'component',
    ]);

    $this->actingAs($this->user)
        ->get('/architecture?project='.$this->project->id)
        ->assertOk()
        ->assertSee('Architecture')
        ->assertSee('Descent stack')
        ->assertSee('GuidanceComputer');
});

test('verification page renders test plans, cases, and anomalies', function () {
    $plan = $this->project->testPlans()->create([
        'level' => 'system',
        'name' => 'Powered descent end-to-end',
        'scope' => 'Full landing profile.',
    ]);
    $plan->cases()->create([
        'name' => 'Nominal burn timing',
        'objective' => 'Verify ignition at T-10s.',
        'expected_results' => 'Engine ignites at T-10s.',
    ]);
    $this->project->anomalies()->create([
        'severity' => 'high',
        'status' => 'open',
        'summary' => 'Telemetry sync drift',
        'description' => 'Subseconds desync between burst windows.',
    ]);

    $this->actingAs($this->user)
        ->get('/verification?project='.$this->project->id)
        ->assertOk()
        ->assertSee('Verification')
        ->assertSee('Powered descent end-to-end')
        ->assertSee('Nominal burn timing')
        ->assertSee('Telemetry sync drift');
});

test('plan page renders milestones, work items, and roles', function () {
    $role = $this->project->roles()->create([
        'name' => 'Propulsion Engineer',
        'weekly_capacity_hours' => 30,
    ]);
    $this->project->milestones()->create([
        'name' => 'Critical Design Review',
        'target_date' => now()->addMonth()->toDateString(),
        'status' => 'pending',
    ]);
    $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Wire the descent engine',
        'status' => 'in_progress',
        'responsible_role_id' => $role->id,
        'effort_estimate_hours' => 8,
    ]);

    $this->actingAs($this->user)
        ->get('/plan?project='.$this->project->id)
        ->assertOk()
        ->assertSee('Plan')
        ->assertSee('Critical Design Review')
        ->assertSee('Wire the descent engine')
        ->assertSee('Propulsion Engineer');
});

test('evidence page renders releases, deployments, and delivery links', function () {
    $release = $this->project->releases()->create([
        'version' => '1.0.0',
        'name' => 'Lunar GA',
        'status' => 'released',
        'released_at' => now()->subDay(),
    ]);
    $this->project->deployments()->create([
        'release_id' => $release->id,
        'environment' => 'production',
        'status' => 'succeeded',
        'deployed_at' => now()->subHour(),
    ]);
    $workItem = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Tune throttle response',
        'status' => 'done',
    ]);
    $link = $workItem->deliveryLinks()->create([
        'type' => 'pull_request',
        'ref' => 'PR-42',
        'url' => 'https://example.test/pr/42',
    ]);
    $link->checkRuns()->create([
        'provider' => 'github',
        'name' => 'unit-tests',
        'status' => 'completed',
        'conclusion' => 'success',
    ]);

    $this->actingAs($this->user)
        ->get('/evidence?project='.$this->project->id)
        ->assertOk()
        ->assertSee('Evidence')
        ->assertSee('1.0.0')
        ->assertSee('production')
        ->assertSee('PR-42')
        ->assertSee('unit-tests');
});

test('resource pages redirect guests to login', function () {
    foreach (['intent', 'capabilities', 'architecture', 'verification', 'plan', 'evidence'] as $path) {
        $this->get('/'.$path)->assertRedirect('/login');
    }
});

test('plan page only lists projects owned by the authed user', function () {
    $bob = User::factory()->create();
    Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Bob project',
        'rigor_level' => 1,
    ]);

    $this->actingAs($this->user)
        ->get('/plan')
        ->assertOk()
        ->assertSee('Lunar Lander')
        ->assertDontSee('Bob project');
});
