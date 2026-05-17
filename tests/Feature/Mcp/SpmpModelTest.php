<?php

use App\Models\Citation;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    $this->aliceProject = Project::create([
        'workspace_id' => $this->alice->active_workspace_id,
        'name' => 'Apollo',
        'rigor_level' => 2,
    ]);
    $this->bobProject = Project::create([
        'workspace_id' => $this->bob->active_workspace_id,
        'name' => 'Bob',
        'rigor_level' => 2,
    ]);
});

it('registers SPMP morph aliases', function () {
    $map = Relation::morphMap();

    expect($map)->toHaveKeys(['project_plan', 'milestone', 'role']);
    expect($map['project_plan'])->toBe(ProjectPlan::class);
    expect($map['milestone'])->toBe(Milestone::class);
    expect($map['role'])->toBe(Role::class);
});

it('keeps only one ProjectPlan per project via unique project_id', function () {
    ProjectPlan::create([
        'project_id' => $this->aliceProject->id,
        'status' => 'draft',
    ]);

    expect(fn () => ProjectPlan::create([
        'project_id' => $this->aliceProject->id,
        'status' => 'active',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('cascades ProjectPlan + milestones + roles when the project is deleted', function () {
    ProjectPlan::create(['project_id' => $this->aliceProject->id]);
    Milestone::create([
        'project_id' => $this->aliceProject->id,
        'name' => 'Beta',
    ]);
    Role::create([
        'project_id' => $this->aliceProject->id,
        'name' => 'QA Lead',
    ]);

    $this->aliceProject->delete();

    expect(ProjectPlan::withoutGlobalScopes()->count())->toBe(0);
    expect(Milestone::withoutGlobalScopes()->count())->toBe(0);
    expect(Role::withoutGlobalScopes()->count())->toBe(0);
});

it('scopes ProjectPlan / milestones / roles to the authenticated owner', function () {
    ProjectPlan::create(['project_id' => $this->aliceProject->id]);
    ProjectPlan::create(['project_id' => $this->bobProject->id]);
    Milestone::create(['project_id' => $this->aliceProject->id, 'name' => 'Alice MS']);
    Milestone::create(['project_id' => $this->bobProject->id, 'name' => 'Bob MS']);
    Role::create(['project_id' => $this->aliceProject->id, 'name' => 'Alice role']);
    Role::create(['project_id' => $this->bobProject->id, 'name' => 'Bob role']);

    auth()->login($this->alice);

    expect(ProjectPlan::count())->toBe(1);
    expect(Milestone::count())->toBe(1);
    expect(Milestone::first()->name)->toBe('Alice MS');
    expect(Role::count())->toBe(1);
    expect(Role::first()->name)->toBe('Alice role');
});

it('enforces unique role name per project', function () {
    Role::create(['project_id' => $this->aliceProject->id, 'name' => 'QA Lead']);

    expect(fn () => Role::create([
        'project_id' => $this->aliceProject->id,
        'name' => 'QA Lead',
    ]))->toThrow(UniqueConstraintViolationException::class);

    // Different project, same name → allowed.
    Role::create(['project_id' => $this->bobProject->id, 'name' => 'QA Lead']);

    expect(Role::withoutGlobalScopes()->where('name', 'QA Lead')->count())->toBe(2);
});

it('lets a Source cite a ProjectPlan / Milestone / Role', function () {
    $source = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Brief',
    ]);
    $plan = ProjectPlan::create(['project_id' => $this->aliceProject->id]);
    $ms = Milestone::create(['project_id' => $this->aliceProject->id, 'name' => 'GA']);
    $role = Role::create(['project_id' => $this->aliceProject->id, 'name' => 'Tech Lead']);

    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'project_plan',
        'citable_id' => $plan->id,
        'locator' => '§6.1',
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'milestone',
        'citable_id' => $ms->id,
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'role',
        'citable_id' => $role->id,
    ]);

    expect($plan->citations()->count())->toBe(1);
    expect($ms->citations()->count())->toBe(1);
    expect($role->citations()->count())->toBe(1);
});
