<?php

use App\Growth\Transitions\AcceptFinding;
use App\Growth\Transitions\DispositionFinding;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Project;
use App\Models\Review;
use App\Models\ReviewFinding;
use App\Models\StatusTransition;
use App\Models\User;
use Livewire\Livewire;

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

// ---- webapp buttons ----

it('shows a disposition button for an open finding and dispositions it', function () {
    $finding = ($this->makeFinding)('open');

    $this->actingAs($this->user);

    Livewire::test('pages::reviews.show', ['review' => $this->review])
        ->assertSee('Disposition')
        ->call('dispositionFinding', $finding->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($finding->fresh()->status)->toBe('dispositioned');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('dispositioned')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id);
});

it('walks a finding through disposition, resolve, and close from the webapp', function () {
    $finding = ($this->makeFinding)('dispositioned');

    $this->actingAs($this->user);

    Livewire::test('pages::reviews.show', ['review' => $this->review])
        ->call('resolveFinding', $finding->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);
    expect($finding->fresh()->status)->toBe('resolved');

    Livewire::test('pages::reviews.show', ['review' => $this->review])
        ->call('closeFinding', $finding->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);
    expect($finding->fresh()->status)->toBe('closed');

    Livewire::test('pages::reviews.show', ['review' => $this->review])
        ->call('reopenFinding', $finding->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);
    expect($finding->fresh()->status)->toBe('open');
});

it('rejects an illegal finding transition from the webapp and warns the user', function () {
    $finding = ($this->makeFinding)('closed');

    $this->actingAs($this->user);

    Livewire::test('pages::reviews.show', ['review' => $this->review])
        ->call('dispositionFinding', $finding->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($finding->fresh()->status)->toBe('closed')
        ->and(StatusTransition::count())->toBe(0);
});

it('rejects transitioning a finding that belongs to another review', function () {
    $otherReview = Review::create([
        'project_id' => $this->project->id,
        'type' => 'inspection',
        'title' => 'Other review',
        'status' => 'in_progress',
    ]);
    $otherFinding = ReviewFinding::create([
        'project_id' => $this->project->id,
        'review_id' => $otherReview->id,
        'title' => 'Belongs elsewhere',
        'severity' => 'low',
        'status' => 'open',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::reviews.show', ['review' => $this->review])
        ->call('dispositionFinding', $otherFinding->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($otherFinding->fresh()->status)->toBe('open')
        ->and(StatusTransition::count())->toBe(0);
});
