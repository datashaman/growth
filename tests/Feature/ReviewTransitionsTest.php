<?php

use App\Growth\Transitions\CancelReview;
use App\Growth\Transitions\CloseReview;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\StartReview;
use App\Models\Project;
use App\Models\Review;
use App\Models\ReviewDecisionEvent;
use App\Models\StatusTransition;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Review transitions',
        'rigor_level' => 2,
    ]);

    $this->makeReview = fn (string $status): Review => Review::create([
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'Architecture review',
        'status' => $status,
    ]);
});

// ---- transition actions ----

it('applies a legal review transition and records a decision event', function () {
    $review = ($this->makeReview)('planned');

    $event = (new StartReview)->apply($review, $this->user, 'Kicking off');

    expect($review->fresh()->status)->toBe('in_progress')
        ->and($event)->toBeInstanceOf(ReviewDecisionEvent::class)
        ->and($event->from_status)->toBe('planned')
        ->and($event->to_status)->toBe('in_progress')
        ->and($event->rationale)->toBe('Kicking off')
        ->and($event->recorded_by_user_id)->toBe($this->user->id)
        ->and(ReviewDecisionEvent::count())->toBe(1)
        ->and(StatusTransition::count())->toBe(0);
});

it('rejects an illegal review source state without writing a decision event', function () {
    $review = ($this->makeReview)('planned');

    expect(fn () => (new CloseReview)->apply($review))
        ->toThrow(IllegalTransitionException::class, 'Cannot close a review that is planned.');

    expect($review->fresh()->status)->toBe('planned')
        ->and(ReviewDecisionEvent::count())->toBe(0);
});

it('cancels a review from either planned or in_progress', function () {
    $planned = ($this->makeReview)('planned');
    $inProgress = ($this->makeReview)('in_progress');

    (new CancelReview)->apply($planned);
    (new CancelReview)->apply($inProgress);

    expect($planned->fresh()->status)->toBe('cancelled')
        ->and($inProgress->fresh()->status)->toBe('cancelled');
});

// ---- page access ----

it('404s the review page for a user from another workspace', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignReview = Review::create([
        'project_id' => $strangerProject->id,
        'type' => 'audit',
        'title' => 'Off limits',
        'status' => 'planned',
    ]);

    $this->actingAs($this->user)
        ->get(route('reviews.show', $foreignReview))
        ->assertNotFound();
});
