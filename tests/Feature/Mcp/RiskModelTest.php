<?php

use App\Models\Citation;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    $this->aliceProject = Project::create([
        'user_id' => $this->alice->id,
        'name' => 'Apollo',
        'rigor_level' => 2,
    ]);
    $this->bobProject = Project::create([
        'user_id' => $this->bob->id,
        'name' => 'Bob',
        'rigor_level' => 2,
    ]);
});

it('registers the risk morph alias', function () {
    $map = Relation::morphMap();

    expect($map)->toHaveKey('risk');
    expect($map['risk'])->toBe(Risk::class);
});

it('relates risks to projects, owner roles, and citations', function () {
    $role = Role::create(['project_id' => $this->aliceProject->id, 'name' => 'Delivery Lead']);
    $source = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Risk workshop',
    ]);
    $risk = Risk::create([
        'project_id' => $this->aliceProject->id,
        'owner_role_id' => $role->id,
        'title' => 'Identity provider outage',
        'category' => 'operational',
        'probability' => 'high',
        'impact' => 'high',
        'status' => 'identified',
    ]);

    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'risk',
        'citable_id' => $risk->id,
        'locator' => 'p. 3',
    ]);

    expect($risk->project->is($this->aliceProject))->toBeTrue();
    expect($risk->ownerRole->is($role))->toBeTrue();
    expect($role->risks()->count())->toBe(1);
    expect($this->aliceProject->risks()->count())->toBe(1);
    expect($risk->citations()->count())->toBe(1);
});

it('nulls owner role when the role is deleted', function () {
    $role = Role::create(['project_id' => $this->aliceProject->id, 'name' => 'Delivery Lead']);
    $risk = Risk::create([
        'project_id' => $this->aliceProject->id,
        'owner_role_id' => $role->id,
        'title' => 'Vendor slippage',
        'category' => 'schedule',
        'probability' => 'medium',
        'impact' => 'high',
    ]);

    $role->delete();

    expect($risk->refresh()->owner_role_id)->toBeNull();
});

it('scopes risks to the authenticated project owner', function () {
    Risk::create([
        'project_id' => $this->aliceProject->id,
        'title' => 'Alice risk',
        'category' => 'technical',
        'probability' => 'low',
        'impact' => 'medium',
    ]);
    Risk::create([
        'project_id' => $this->bobProject->id,
        'title' => 'Bob risk',
        'category' => 'technical',
        'probability' => 'low',
        'impact' => 'medium',
    ]);

    auth()->login($this->alice);

    expect(Risk::count())->toBe(1);
    expect(Risk::first()->title)->toBe('Alice risk');
});
