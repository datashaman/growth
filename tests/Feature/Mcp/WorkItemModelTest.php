<?php

use App\Models\Citation;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Relations\Relation;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    $this->aliceProject = Project::create([
        'user_id' => $this->alice->id,
        'name' => 'Apollo',
        'integrity_level' => 2,
    ]);
    $this->bobProject = Project::create([
        'user_id' => $this->bob->id,
        'name' => 'Bob',
        'integrity_level' => 2,
    ]);
});

it('registers the work_item morph alias', function () {
    expect(Relation::morphMap())->toHaveKey('work_item');
    expect(Relation::morphMap()['work_item'])->toBe(WorkItem::class);
});

it('builds a parent/child hierarchy', function () {
    $root = WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'deliverable',
        'name' => 'Auth subsystem',
    ]);
    $child = WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'parent_id' => $root->id,
        'kind' => 'work_package',
        'name' => 'SSO integration',
    ]);
    $grand = WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'parent_id' => $child->id,
        'kind' => 'task',
        'name' => 'Wire SAML callback',
    ]);

    expect($root->children->pluck('id')->all())->toBe([$child->id]);
    expect($child->parent->id)->toBe($root->id);
    expect($grand->parent->parent->id)->toBe($root->id);
});

it('promotes children to root when their parent is deleted (nullOnDelete)', function () {
    $root = WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'deliverable',
        'name' => 'Auth subsystem',
    ]);
    $child = WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'parent_id' => $root->id,
        'kind' => 'task',
        'name' => 'Wire SAML callback',
    ]);

    $root->delete();

    expect(WorkItem::find($child->id)->parent_id)->toBeNull();
});

it('clears responsible_role_id when the role is deleted (nullOnDelete)', function () {
    $role = Role::create([
        'project_id' => $this->aliceProject->id,
        'name' => 'QA Lead',
    ]);
    $item = WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'responsible_role_id' => $role->id,
        'kind' => 'task',
        'name' => 'Smoke test',
    ]);

    $role->delete();

    expect(WorkItem::find($item->id)->responsible_role_id)->toBeNull();
});

it('attaches requirements many-to-many with pivot uniqueness', function () {
    $item = WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'task',
        'name' => 'Build SSO',
    ]);
    $req = Requirement::create([
        'project_id' => $this->aliceProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Auth via SSO.',
    ]);

    $item->requirements()->attach($req->id);

    // Re-attach with attach() throws unique-violation; syncWithoutDetaching is the safe idempotent path.
    $item->requirements()->syncWithoutDetaching([$req->id]);

    expect($item->requirements()->count())->toBe(1);
    expect($req->workItems()->count())->toBe(1);
});

it('attaches milestones many-to-many', function () {
    $item = WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'task',
        'name' => 'Build SSO',
    ]);
    $ms = Milestone::create([
        'project_id' => $this->aliceProject->id,
        'name' => 'Beta',
    ]);

    $item->milestones()->attach($ms->id);

    expect($item->milestones()->count())->toBe(1);
    expect($ms->workItems()->count())->toBe(1);
});

it('scopes WorkItems to the authenticated owner', function () {
    WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'task',
        'name' => 'Alice task',
    ]);
    WorkItem::create([
        'project_id' => $this->bobProject->id,
        'kind' => 'task',
        'name' => 'Bob task',
    ]);

    auth()->login($this->alice);

    expect(WorkItem::count())->toBe(1);
    expect(WorkItem::first()->name)->toBe('Alice task');
});

it('lets a Source cite a WorkItem', function () {
    $source = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Brief',
    ]);
    $item = WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'deliverable',
        'name' => 'Auth subsystem',
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'work_item',
        'citable_id' => $item->id,
        'locator' => '§3',
    ]);

    expect($item->citations()->count())->toBe(1);
});

it('cascades pivot rows when work_item is deleted', function () {
    $item = WorkItem::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'task',
        'name' => 'X',
    ]);
    $req = Requirement::create([
        'project_id' => $this->aliceProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'R',
    ]);
    $ms = Milestone::create([
        'project_id' => $this->aliceProject->id,
        'name' => 'M',
    ]);
    $item->requirements()->attach($req->id);
    $item->milestones()->attach($ms->id);

    $item->delete();

    expect(DB::table('requirement_work_item')->count())->toBe(0);
    expect(DB::table('milestone_work_item')->count())->toBe(0);
});
