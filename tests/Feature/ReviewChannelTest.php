<?php

use App\Events\ReviewDataChanged;
use App\Models\Project;
use App\Models\Review;
use App\Models\ReviewFinding;
use App\Models\Role;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $this->review = Review::create([
        'project_id' => $this->project->id,
        'type' => 'inspection',
        'title' => 'SRS inspection',
        'status' => 'planned',
    ]);

    $this->role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Moderator',
    ]);
});

test('saving a Review dispatches ReviewDataChanged on its review channel', function () {
    Event::fake([ReviewDataChanged::class]);

    $this->review->update(['status' => 'held']);

    Event::assertDispatched(
        ReviewDataChanged::class,
        function (ReviewDataChanged $event): bool {
            $channels = $event->broadcastOn();

            return $event->reviewId === (string) $this->review->id
                && count($channels) === 1
                && $channels[0] instanceof PrivateChannel
                && $channels[0]->name === 'private-reviews.'.$this->review->id;
        },
    );
});

test('saving a ReviewFinding dispatches ReviewDataChanged scoped to the review', function () {
    Event::fake([ReviewDataChanged::class]);

    ReviewFinding::create([
        'project_id' => $this->project->id,
        'review_id' => $this->review->id,
        'title' => 'Acceptance criteria missing',
        'severity' => 'high',
        'status' => 'open',
    ]);

    Event::assertDispatched(
        ReviewDataChanged::class,
        fn (ReviewDataChanged $e) => $e->reviewId === (string) $this->review->id,
    );
});

test('saving a ReviewParticipant and a ReviewTarget each dispatch on the review channel', function () {
    Event::fake([ReviewDataChanged::class]);

    $this->review->participants()->create([
        'role_id' => $this->role->id,
        'responsibility' => 'moderator',
        'attendance_status' => 'attended',
    ]);

    $this->review->targets()->create([
        'reviewable_type' => 'requirement',
        'reviewable_id' => 'fake-id',
        'context' => 'unit test',
    ]);

    Event::assertDispatchedTimes(ReviewDataChanged::class, 2);
});

test('reviews.show subscribes to the reviews channel keyed off the loaded review', function () {
    $listeners = Livewire::test('pages::reviews.show', ['review' => $this->review])
        ->instance()->getListeners();

    expect($listeners)->toHaveKey('echo-private:reviews.'.$this->review->id.',ReviewDataChanged');
});
