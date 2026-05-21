<?php

use App\Growth\Lint\ChangeLinter;
use App\Growth\Transitions\ApproveChangeRequest;
use App\Growth\Transitions\DeferChangeRequest;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\RejectChangeRequest;
use App\Growth\Transitions\SubmitChangeRequest;
use App\Models\ChangeApprovalEvent;
use App\Models\ChangeImpact;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Transitions',
        'rigor_level' => 2,
    ]);

    $this->makeChange = fn (string $status, ?string $decision = null): ChangeRequest => ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Change',
        'category' => 'scope',
        'priority' => 'medium',
        'status' => $status,
        'decision' => $decision,
    ]);
});

// ---- base transition action ----

it('applies a legal change request transition and records an approval event', function () {
    $change = ($this->makeChange)('under_review');

    $event = (new ApproveChangeRequest)->apply($change, $this->user, 'Approved at CCB');

    $change->refresh();
    expect($change->status)->toBe('approved')
        ->and($change->decision)->toBe('approved')
        ->and($change->decided_at)->not->toBeNull()
        ->and($event->from_status)->toBe('under_review')
        ->and($event->to_status)->toBe('approved')
        ->and($event->to_decision)->toBe('approved')
        ->and($event->rationale)->toBe('Approved at CCB')
        ->and($event->recorded_by_user_id)->toBe($this->user->id)
        ->and(ChangeApprovalEvent::count())->toBe(1);
});

it('stamps the reason onto the change request as the decision rationale', function (string $transition) {
    $change = ($this->makeChange)('under_review');

    (new $transition)->apply($change, $this->user, 'Recorded at CCB');

    expect($change->fresh()->decision_rationale)->toBe('Recorded at CCB');
})->with([
    'approve' => ApproveChangeRequest::class,
    'reject' => RejectChangeRequest::class,
    'defer' => DeferChangeRequest::class,
]);

it('leaves an existing decision rationale intact when the reason is blank', function () {
    $change = ($this->makeChange)('under_review');
    $change->forceFill(['decision_rationale' => 'pre-existing'])->save();

    (new ApproveChangeRequest)->apply($change, $this->user, '   ');

    expect($change->fresh()->decision_rationale)->toBe('pre-existing');
});

it('clears the change.decision_rationale.empty lint once a decision carries a reason', function () {
    $change = ($this->makeChange)('under_review');
    ChangeImpact::create([
        'change_request_id' => $change->id,
        'impactable_type' => 'requirement',
        'impactable_id' => 'rq-placeholder',
        'impact_kind' => 'modifies',
    ]);

    (new ApproveChangeRequest)->apply($change, $this->user, 'Approved at CCB');

    $rules = collect((new ChangeLinter)->check($this->project->fresh()))->pluck('rule');
    expect($rules)->not->toContain('change.decision_rationale.empty');
});

it('rejects an illegal source state without writing an approval event', function () {
    $change = ($this->makeChange)('proposed');

    expect(fn () => (new ApproveChangeRequest)->apply($change))
        ->toThrow(IllegalTransitionException::class, 'Cannot approve a change request that is proposed.');

    expect($change->fresh()->status)->toBe('proposed')
        ->and(ChangeApprovalEvent::count())->toBe(0);
});

it('records a null actor when no user is supplied', function () {
    $change = ($this->makeChange)('proposed');

    $event = (new SubmitChangeRequest)->apply($change);

    expect($event->recorded_by_user_id)->toBeNull()
        ->and($change->fresh()->status)->toBe('under_review');
});

// ---- page access ----

it('404s the change request page for a user from another workspace', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignChange = ChangeRequest::create([
        'project_id' => $strangerProject->id,
        'title' => 'Off limits',
        'category' => 'scope',
        'priority' => 'medium',
        'status' => 'proposed',
    ]);

    $this->actingAs($this->user)
        ->get(route('change-requests.show', $foreignChange))
        ->assertNotFound();
});
