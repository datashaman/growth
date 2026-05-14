<?php

use App\Models\Deployment;
use App\Models\Project;
use App\Models\Release;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

test('owner can create a release', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::releases.create-modal', ['projectId' => $this->project->id])
        ->set('version', '1.0.0')
        ->set('name', 'First flight')
        ->set('status', 'released')
        ->set('released_at', '2026-04-01')
        ->call('save')
        ->assertHasNoErrors();

    $release = Release::query()->where('version', '1.0.0')->first();
    expect($release)->not->toBeNull()
        ->and($release->project_id)->toBe($this->project->id);
});

test('release version is unique per project', function () {
    $this->project->releases()->create(['version' => '1.0.0']);
    $this->actingAs($this->user);

    Livewire::test('pages::releases.create-modal', ['projectId' => $this->project->id])
        ->set('version', '1.0.0')
        ->call('save')
        ->assertHasErrors('version');
});

test('release version uniqueness scoped to project', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'user_id' => $other->id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $otherProject->releases()->create(['version' => '1.0.0']);
    $this->actingAs($this->user);

    Livewire::test('pages::releases.create-modal', ['projectId' => $this->project->id])
        ->set('version', '1.0.0')
        ->call('save')
        ->assertHasNoErrors();
});

test('owner can edit a release', function () {
    $release = $this->project->releases()->create([
        'version' => '0.9', 'status' => 'planned',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::releases.edit-modal')
        ->call('load', $release->id)
        ->set('status', 'released')
        ->call('save')
        ->assertHasNoErrors();

    expect($release->fresh()->status)->toBe('released');
});

test('release edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $bobRelease = $bobProject->releases()->create(['version' => '1.0']);
    $this->actingAs($this->user);

    Livewire::test('pages::releases.edit-modal')
        ->call('load', $bobRelease->id)
        ->assertStatus(404);
});

test('owner can delete a release', function () {
    $release = $this->project->releases()->create(['version' => '0.9']);
    $this->actingAs($this->user);

    Livewire::test('pages::releases.delete-modal')
        ->call('load', $release->id)
        ->call('delete');

    expect(Release::find($release->id))->toBeNull();
});

test('release delete surfaces deployment-count warning', function () {
    $release = $this->project->releases()->create(['version' => '1.0']);
    $this->project->deployments()->create([
        'environment' => 'prod',
        'status' => 'succeeded',
        'release_id' => $release->id,
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::releases.delete-modal')
        ->call('load', $release->id)
        ->assertSet('deploymentCount', 1);
});

test('owner can create a deployment with no release', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::deployments.create-modal', ['projectId' => $this->project->id])
        ->set('environment', 'staging')
        ->set('status', 'succeeded')
        ->call('save')
        ->assertHasNoErrors();

    $deployment = Deployment::query()->where('environment', 'staging')->first();
    expect($deployment)->not->toBeNull()
        ->and($deployment->project_id)->toBe($this->project->id)
        ->and($deployment->release_id)->toBeNull();
});

test('deployment can link to a release in the same project', function () {
    $release = $this->project->releases()->create(['version' => '1.0']);
    $this->actingAs($this->user);

    Livewire::test('pages::deployments.create-modal', ['projectId' => $this->project->id])
        ->set('environment', 'prod')
        ->set('status', 'succeeded')
        ->set('release_id', $release->id)
        ->call('save')
        ->assertHasNoErrors();

    $deployment = Deployment::query()->where('environment', 'prod')->first();
    expect($deployment->release_id)->toBe($release->id);
});

test('deployment rejects release from another project', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $foreignRelease = $bobProject->releases()->create(['version' => '9.0']);
    $this->actingAs($this->user);

    Livewire::test('pages::deployments.create-modal', ['projectId' => $this->project->id])
        ->set('environment', 'prod')
        ->set('status', 'succeeded')
        ->set('release_id', $foreignRelease->id)
        ->call('save')
        ->assertHasErrors(['release_id']);
});

test('owner can edit a deployment', function () {
    $deployment = $this->project->deployments()->create([
        'environment' => 'prod', 'status' => 'planned',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::deployments.edit-modal')
        ->call('load', $deployment->id)
        ->set('status', 'succeeded')
        ->call('save')
        ->assertHasNoErrors();

    expect($deployment->fresh()->status)->toBe('succeeded');
});

test('owner can delete a deployment', function () {
    $deployment = $this->project->deployments()->create([
        'environment' => 'prod', 'status' => 'planned',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::deployments.delete-modal')
        ->call('load', $deployment->id)
        ->call('delete');

    expect(Deployment::find($deployment->id))->toBeNull();
});

test('evidence page renders New release + New deployment buttons', function () {
    $this->actingAs($this->user)
        ->get('/evidence?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New release')
        ->assertSee('New deployment');
});
