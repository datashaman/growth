<?php

use App\Models\Project;
use App\Models\Review;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Lunar Lander',
        'integrity_level' => 2,
    ]);
});

test('owner can create a review', function () {
    $role = $this->project->roles()->create(['name' => 'Chair']);

    $this->actingAs($this->user);

    Livewire::test('pages::reviews.create-modal', ['projectId' => $this->project->id])
        ->set('title', 'Heat shield design review')
        ->set('type', 'technical_review')
        ->set('status', 'planned')
        ->set('owner_role_id', $role->id)
        ->set('objective', 'Confirm thermal margins.')
        ->set('entry_criteria_text', "Drawings released\nAnalysis complete")
        ->set('exit_criteria_text', 'Decision recorded')
        ->call('save')
        ->assertHasNoErrors();

    $review = Review::query()->where('title', 'Heat shield design review')->first();
    expect($review)->not->toBeNull()
        ->and($review->project_id)->toBe($this->project->id)
        ->and($review->owner_role_id)->toBe($role->id)
        ->and($review->entry_criteria)->toBe(['Drawings released', 'Analysis complete'])
        ->and($review->exit_criteria)->toBe(['Decision recorded']);
});

test('review create requires title and type', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::reviews.create-modal', ['projectId' => $this->project->id])
        ->set('title', '')
        ->set('type', '')
        ->call('save')
        ->assertHasErrors(['title' => 'required', 'type']);
});

test('review create rejects foreign owner_role_id', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::create([
        'user_id' => $otherUser->id,
        'name' => 'Other',
        'integrity_level' => 1,
    ]);
    $foreignRole = $otherProject->roles()->create(['name' => 'Spy']);

    $this->actingAs($this->user);

    Livewire::test('pages::reviews.create-modal', ['projectId' => $this->project->id])
        ->set('title', 'X')
        ->set('owner_role_id', $foreignRole->id)
        ->call('save')
        ->assertHasErrors(['owner_role_id']);

    expect(Review::query()->count())->toBe(0);
});

test('review create projectId is locked', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Hostile',
        'integrity_level' => 1,
    ]);

    $this->actingAs($this->user);

    expect(fn () => Livewire::test('pages::reviews.create-modal', ['projectId' => $this->project->id])
        ->set('projectId', $bobProject->id))
        ->toThrow(Exception::class);
});

test('owner can edit a review', function () {
    $review = $this->project->reviews()->create([
        'title' => 'Original',
        'type' => 'technical_review',
        'status' => 'planned',
        'entry_criteria' => ['A', 'B'],
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::reviews.edit-modal', ['reviewId' => $review->id])
        ->assertSet('title', 'Original')
        ->assertSet('entry_criteria_text', "A\nB")
        ->set('title', 'Updated')
        ->set('status', 'held')
        ->set('decision', 'accepted')
        ->call('save')
        ->assertHasNoErrors();

    $review->refresh();
    expect($review->title)->toBe('Updated')
        ->and($review->status)->toBe('held')
        ->and($review->decision)->toBe('accepted');
});

test('review edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Other',
        'integrity_level' => 1,
    ]);
    $bobReview = $bobProject->reviews()->create([
        'title' => 'Bob',
        'type' => 'technical_review',
        'status' => 'planned',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::reviews.edit-modal', ['reviewId' => $bobReview->id])
        ->assertStatus(404);
});

test('owner can delete a review', function () {
    $review = $this->project->reviews()->create([
        'title' => 'X',
        'type' => 'technical_review',
        'status' => 'planned',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::reviews.delete-modal', ['reviewId' => $review->id])
        ->call('delete');

    expect(Review::find($review->id))->toBeNull();
});

test('review delete 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Other',
        'integrity_level' => 1,
    ]);
    $bobReview = $bobProject->reviews()->create([
        'title' => 'Bob',
        'type' => 'technical_review',
        'status' => 'planned',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::reviews.delete-modal', ['reviewId' => $bobReview->id])
        ->call('delete')
        ->assertStatus(404);

    expect(Review::withoutGlobalScopes()->find($bobReview->id))->not->toBeNull();
});

test('dashboard renders New review button for project owner', function () {
    $this->actingAs($this->user)
        ->get('/dashboard?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New review');
});
