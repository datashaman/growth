<?php

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Capability;
use Livewire\Livewire;

/**
 * Create a project whose dashboard exercises every panel: counts, readiness,
 * implementation, risks, anomalies, and reviews.
 */
function dashboardProjectWithEveryPanel(User $user): Project
{
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $project->workItems()->create([
        'kind' => 'task',
        'name' => 'Wire the descent engine',
        'status' => 'todo',
    ]);
    $project->risks()->create([
        'title' => 'Heat shield delamination',
        'category' => 'technical',
        'probability' => 'high',
        'impact' => 'high',
        'status' => 'mitigating',
    ]);
    $project->anomalies()->create([
        'severity' => 'high',
        'status' => 'open',
        'summary' => 'Telemetry sync drift',
        'description' => 'Subseconds desync between burst windows.',
        'environment' => 'staging',
    ]);
    $project->reviews()->create([
        'type' => 'technical_review',
        'title' => 'Heat shield design review',
        'status' => 'held',
        'decision' => 'accepted_with_actions',
        'held_at' => now()->subDay(),
    ]);

    return $project;
}

function dashboardRole(User $user, Project $project, array $capabilities): Role
{
    $role = Role::create([
        'project_id' => $project->id,
        'name' => fake()->unique()->jobTitle(),
    ]);

    $role->syncCapabilities($capabilities);
    $role->users()->attach($user);

    return $role;
}

test('dashboard redirects guests to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('dashboard renders for an authed user with no projects', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Project Dashboard')
        ->assertSee('No projects yet');
});

test('dashboard renders sections for the selected project', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'description' => 'Mission control.',
        'rigor_level' => 3,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Lunar Lander')
        ->assertSee('Mission control.')
        ->assertSee('Stakeholders')
        ->assertSee('Readiness')
        ->assertSee('Implementation')
        ->assertSee('Risks')
        ->assertSee('Anomalies')
        ->assertSee('Reviews')
        ->assertDontSee('Counts');
});

test('dashboard readiness gates render their findings inline', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $project->requirements()->create([
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall do something TBD.',
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Readiness')
        ->assertSee('rule: requirement contains TBD/TBS/TBR — not complete')
        ->assertSee('aria-controls="gate-findings-requirements"', false)
        ->assertSee('id="gate-findings-requirements"', false);
});

test('dashboard implementation table lists work items', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $project->workItems()->create([
        'kind' => 'task',
        'name' => 'Wire the descent engine',
        'status' => 'done',
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Wire the descent engine')
        ->assertSee('done without evidence');
});

test('dashboard surfaces risks, anomalies, and reviews', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $project->risks()->create([
        'title' => 'Heat shield delamination',
        'category' => 'technical',
        'probability' => 'high',
        'impact' => 'high',
        'status' => 'mitigating',
    ]);
    $project->anomalies()->create([
        'severity' => 'high',
        'status' => 'open',
        'summary' => 'Telemetry sync drift',
        'description' => 'Subseconds desync between burst windows.',
        'environment' => 'staging',
    ]);
    $project->reviews()->create([
        'type' => 'technical_review',
        'title' => 'Heat shield design review',
        'status' => 'held',
        'decision' => 'accepted_with_actions',
        'held_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Heat shield delamination')
        ->assertSee('Telemetry sync drift')
        ->assertSee('Heat shield design review')
        ->assertSee('accepted with actions');
});

/*
 * Each panel is identified by a string unique to it: a count tile label
 * ("Stakeholders") or a table column header ("Gate" for readiness,
 * "Deploys" for implementation, "Exposure" for risks,
 * "Environment" for anomalies, "Decision" for reviews). Neither entity titles
 * nor the word "Implementation" are used as anchors — the readiness panel
 * cross-references risks, anomalies, reviews, and work items as finding
 * subjects, and lists an "Implementation" readiness gate, so those strings can
 * surface outside their own panels.
 */

test('the All lens renders every dashboard panel', function () {
    $user = User::factory()->create();
    $project = dashboardProjectWithEveryPanel($user);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Stakeholders')
        ->assertSee('Gate')
        ->assertSee('Deploys')
        ->assertSee('Exposure')
        ->assertSee('Environment')
        ->assertSee('Decision');
});

test('a requirements role renders only counts and readiness', function () {
    $user = User::factory()->create();
    $project = dashboardProjectWithEveryPanel($user);
    dashboardRole($user, $project, [
        Capability::ManageIntent,
        Capability::ManageRequirements,
        Capability::ManageArchitecture,
        Capability::ViewDashboard,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Stakeholders')
        ->assertSee('Gate')
        ->assertDontSee('Deploys')
        ->assertDontSee('Exposure')
        ->assertDontSee('Environment')
        ->assertDontSee('Decision');
});

test('an implementation role renders implementation, risks, and anomalies', function () {
    $user = User::factory()->create();
    $project = dashboardProjectWithEveryPanel($user);
    dashboardRole($user, $project, [
        Capability::ManagePlan,
        Capability::ManageVerification,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Deploys')
        ->assertSee('Exposure')
        ->assertSee('Environment')
        ->assertDontSee('Stakeholders')
        ->assertDontSee('Gate')
        ->assertDontSee('Decision');
});

test('a reviewer role renders only readiness and reviews', function () {
    $user = User::factory()->create();
    $project = dashboardProjectWithEveryPanel($user);
    dashboardRole($user, $project, [
        Capability::ManageRequirements,
        Capability::ManageChanges,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Gate')
        ->assertSee('Decision')
        ->assertDontSee('Stakeholders')
        ->assertDontSee('Deploys')
        ->assertDontSee('Exposure')
        ->assertDontSee('Environment');
});

test('dashboard only lists projects owned by the authed user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Project::create([
        'workspace_id' => $alice->active_workspace_id,
        'name' => 'Alice project',
        'rigor_level' => 1,
    ]);
    Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Bob project',
        'rigor_level' => 1,
    ]);

    $this->actingAs($alice)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Alice project')
        ->assertDontSee('Bob project');
});

test('dashboard my queue panel lists items routed to the viewer', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $role = Role::create(['project_id' => $project->id, 'name' => 'Builder']);
    $user->roles()->attach($role);

    $project->workItems()->create([
        'kind' => 'task',
        'name' => 'Wire the descent engine',
        'status' => 'blocked',
        'responsible_role_id' => $role->id,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('My Queue')
        ->assertSee('Wire the descent engine');
});

test('dashboard my queue panel always shows and lists unowned lint errors', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    // The viewer holds no role, so nothing is routed to them — but the panel
    // still renders and surfaces lint errors on artifacts with no owning role.
    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('My Queue')
        ->assertSee('lint error (unowned)');
});

/*
 * #363: dashboard polish — the panels below each fix one reported defect.
 */

test('#363 readiness panel is not boxed into a half-width grid', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    // The Readiness section used to sit alone in a two-column grid, rendering at
    // half width. No panel on the dashboard should use that wrapper now.
    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Readiness')
        ->assertDontSee('lg:grid-cols-2', false);
});

test('#363 count tiles link to the section that lists their entities', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('href="'.route('requirements', ['project' => $project->id]).'"', false)
        ->assertSee('href="'.route('architecture', ['project' => $project->id]).'"', false)
        ->assertSee('href="'.route('verification', ['project' => $project->id]).'"', false)
        ->assertSee('href="'.route('plan', ['project' => $project->id]).'"', false)
        ->assertSee('href="'.route('changes', ['project' => $project->id]).'"', false)
        ->assertSee('href="'.route('evidence', ['project' => $project->id]).'"', false);
});

test('dashboard count tiles use a dense desktop grid without a redundant heading', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('xl:grid-cols-10', false)
        ->assertSee('Stakeholders')
        ->assertDontSee('Counts');
});

test('#363 my queue humanizes lint rule codes instead of showing raw rule strings', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    // A project with no plan raises the project-scoped pmp.missing lint finding.
    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('PMP missing')
        ->assertDontSee('pmp.missing');
});

test('#363 my queue resolves project-scoped lint findings to the Plan page', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    session(['selected_project_id' => $project->id]);

    $this->actingAs($user);
    $subjects = Livewire::test('pages::dashboard')->instance()->queueLintSubjects;

    expect($subjects)->toHaveKey('project:'.$project->id);
    expect($subjects['project:'.$project->id]['route'])->toBe(route('plan'));
});

test('#363 implementation panel previews the top work items and links to the full list', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    foreach (range(1, 9) as $n) {
        $project->workItems()->create([
            'kind' => 'task',
            'name' => sprintf('Build subsystem %02d', $n),
            'status' => 'todo',
        ]);
    }

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Build subsystem 01')
        ->assertSee('Build subsystem 08')
        ->assertDontSee('Build subsystem 09')
        ->assertSee('Ordered by failed checks, blocked work, active work, then planned work.')
        ->assertSee('View all 9 work items in Plan');
});

test('#363 implementation panel surfaces blocked and in-progress work before idle work', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $project->workItems()->create(['kind' => 'task', 'name' => 'Idle item', 'status' => 'todo']);
    $project->workItems()->create(['kind' => 'task', 'name' => 'Active item', 'status' => 'in_progress']);
    $project->workItems()->create(['kind' => 'task', 'name' => 'Stuck item', 'status' => 'blocked']);

    $content = $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->getContent();

    $blocked = strpos($content, 'Stuck item');
    $inProgress = strpos($content, 'Active item');
    $idle = strpos($content, 'Idle item');

    expect($blocked)->toBeLessThan($inProgress);
    expect($inProgress)->toBeLessThan($idle);
});
