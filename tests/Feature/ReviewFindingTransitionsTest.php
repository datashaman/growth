<?php

use App\Growth\Transitions\AcceptFinding;
use App\Growth\Transitions\DispositionFinding;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Project;
use App\Models\Review;
use App\Models\ReviewFinding;
use App\Models\StatusTransition;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Finding transitions',
        'rigor_level' => 2,
    ]);

    $this->review = Review::create([
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'Architecture review',
        'status' => 'in_progress',
    ]);

    $this->makeFinding = fn (string $status): ReviewFinding => ReviewFinding::create([
        'project_id' => $this->project->id,
        'review_id' => $this->review->id,
        'title' => 'Missing error handling',
        'severity' => 'high',
        'status' => $status,
    ]);
});

// ---- transition actions ----

it('applies a legal finding transition and records an audit row', function () {
    $finding = ($this->makeFinding)('open');

    $transition = (new DispositionFinding)->apply($finding, $this->user, 'Triaged');

    expect($finding->fresh()->status)->toBe('dispositioned')
        ->and($transition->from_status)->toBe('open')
        ->and($transition->to_status)->toBe('dispositioned')
        ->and($transition->reason)->toBe('Triaged')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and(StatusTransition::count())->toBe(1);
});

it('rejects an illegal finding source state without writing an audit row', function () {
    $finding = ($this->makeFinding)('closed');

    expect(fn () => (new DispositionFinding)->apply($finding))
        ->toThrow(IllegalTransitionException::class, 'Cannot disposition a review finding that is closed.');

    expect($finding->fresh()->status)->toBe('closed')
        ->and(StatusTransition::count())->toBe(0);
});

it('accepts a finding from either open or dispositioned', function () {
    $open = ($this->makeFinding)('open');
    $dispositioned = ($this->makeFinding)('dispositioned');

    (new AcceptFinding)->apply($open);
    (new AcceptFinding)->apply($dispositioned);

    expect($open->fresh()->status)->toBe('accepted')
        ->and($dispositioned->fresh()->status)->toBe('accepted');
});
