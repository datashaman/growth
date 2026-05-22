<?php

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Capability;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Festival Market',
        'rigor_level' => 2,
    ]);
});

test('the reviews index lists the project reviews and links each to its detail page', function () {
    $review = $this->project->reviews()->create([
        'type' => 'technical_review',
        'title' => 'Heat shield design review',
        'status' => 'held',
        'decision' => 'accepted_with_actions',
        'held_at' => now()->subDay(),
    ]);

    $this->actingAs($this->user)
        ->get('/reviews?project='.$this->project->id)
        ->assertOk()
        ->assertSee('Heat shield design review')
        ->assertSee('accepted with actions')
        ->assertSee(route('reviews.show', $review), false);
});

test('the reviews index shows an empty state when there are no reviews', function () {
    $this->actingAs($this->user)
        ->get('/reviews?project='.$this->project->id)
        ->assertOk()
        ->assertSee('No reviews yet.');
});

test('a review in another project is not listed', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);
    $otherProject->reviews()->create([
        'type' => 'audit',
        'title' => 'Foreign audit',
        'status' => 'planned',
    ]);

    $this->actingAs($this->user)
        ->get('/reviews?project='.$this->project->id)
        ->assertOk()
        ->assertDontSee('Foreign audit');
});

test('the Reviews nav item shows for the manage-changes capability and hides without it', function () {
    $manage = Role::create(['project_id' => $this->project->id, 'name' => 'Change Manager']);
    $manage->syncCapabilities([Capability::ManageChanges]);
    $manage->users()->attach($this->user);

    $this->actingAs($this->user)
        ->get('/dashboard?project='.$this->project->id)
        ->assertOk()
        ->assertSee(route('reviews'));

    $viewerOnly = User::factory()->create();
    $viewerProject = Project::create([
        'workspace_id' => $viewerOnly->active_workspace_id,
        'name' => 'Dashboard Only',
        'rigor_level' => 2,
    ]);
    $viewerRole = Role::create(['project_id' => $viewerProject->id, 'name' => 'Dashboard Viewer']);
    $viewerRole->syncCapabilities([Capability::ViewDashboard]);
    $viewerRole->users()->attach($viewerOnly);

    $this->actingAs($viewerOnly)
        ->get('/dashboard?project='.$viewerProject->id)
        ->assertOk()
        ->assertDontSee(route('reviews'));
});
