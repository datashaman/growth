<?php

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;

beforeEach(function () {
    $user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Apollo',
        'rigor_level' => 2,
    ]);
});

// ---- dependencies ----

it('records a precedence edge between two work items', function () {
    $a = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'A']);
    $b = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'B']);

    $b->dependencies()->attach($a->id);

    expect($b->dependencies()->count())->toBe(1);
    expect($b->dependencies->first()->id)->toBe($a->id);
    expect($a->dependents()->count())->toBe(1);
    expect($a->dependents->first()->id)->toBe($b->id);
});

it('supports multiple dependency edges between distinct pairs', function () {
    $a = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'A']);
    $b = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'B']);
    $c = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'C']);

    $c->dependencies()->attach($a->id);
    $c->dependencies()->attach($b->id);

    expect($c->dependencies()->count())->toBe(2);
});

it('cascades dependency rows when a work item is deleted', function () {
    $a = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'A']);
    $b = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'B']);
    $b->dependencies()->attach($a->id);

    $a->delete();

    expect(DB::table('work_item_dependencies')->count())->toBe(0);
    expect(WorkItem::find($b->id))->not->toBeNull();
});

// ---- RACI ----

it('attaches a role to a work item with a RACI label', function () {
    $item = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'X']);
    $tl = Role::create(['project_id' => $this->project->id, 'name' => 'Tech Lead']);
    $qa = Role::create(['project_id' => $this->project->id, 'name' => 'QA Lead']);

    $item->raciRoles()->attach($tl->id, ['raci' => 'r']);
    $item->raciRoles()->attach($qa->id, ['raci' => 'c']);

    $byRaci = $item->raciRoles->keyBy(fn ($r) => $r->pivot->raci);
    expect($byRaci->keys()->all())->toEqualCanonicalizing(['r', 'c']);
    expect($byRaci['r']->name)->toBe('Tech Lead');
});

it('allows the same role under multiple RACI labels on one work item', function () {
    $item = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'X']);
    $tl = Role::create(['project_id' => $this->project->id, 'name' => 'Tech Lead']);

    $item->raciRoles()->attach($tl->id, ['raci' => 'r']);
    $item->raciRoles()->attach($tl->id, ['raci' => 'a']);

    expect($item->raciRoles()->count())->toBe(2);
});

it('cascades RACI rows when work item or role is deleted', function () {
    $item = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'X']);
    $tl = Role::create(['project_id' => $this->project->id, 'name' => 'Tech Lead']);
    $item->raciRoles()->attach($tl->id, ['raci' => 'r']);

    $tl->delete();
    expect(DB::table('raci_assignments')->count())->toBe(0);

    $item2 = WorkItem::create(['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'Y']);
    $tl2 = Role::create(['project_id' => $this->project->id, 'name' => 'TL2']);
    $item2->raciRoles()->attach($tl2->id, ['raci' => 'a']);
    $item2->delete();
    expect(DB::table('raci_assignments')->count())->toBe(0);
});
