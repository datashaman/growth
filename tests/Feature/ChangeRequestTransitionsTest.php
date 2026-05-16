<?php

use App\Growth\Transitions\ApproveChangeRequest;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\SubmitChangeRequest;
use App\Models\ChangeApprovalEvent;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

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

// ---- webapp buttons ----

it('shows a submit button for a proposed change request and submits it', function () {
    $change = ($this->makeChange)('proposed');

    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.show', ['changeRequest' => $change])
        ->assertSee('Submit')
        ->call('submitChangeRequest')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($change->fresh()->status)->toBe('under_review')
        ->and(ChangeApprovalEvent::query()->sole()->to_status)->toBe('under_review');
});

it('shows approve and reject buttons for an under_review change request and approves it', function () {
    $change = ($this->makeChange)('under_review');

    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.show', ['changeRequest' => $change])
        ->assertSee('Approve')
        ->assertSee('Reject')
        ->call('approveChangeRequest')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    $change->refresh();
    expect($change->status)->toBe('approved')
        ->and($change->decision)->toBe('approved');
});

it('rejects an under_review change request from the webapp', function () {
    $change = ($this->makeChange)('under_review');

    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.show', ['changeRequest' => $change])
        ->call('rejectChangeRequest')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    $change->refresh();
    expect($change->status)->toBe('rejected')
        ->and($change->decision)->toBe('rejected')
        ->and(ChangeApprovalEvent::query()->sole()->to_status)->toBe('rejected');
});

it('defers an under_review change request from the webapp', function () {
    $change = ($this->makeChange)('under_review');

    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.show', ['changeRequest' => $change])
        ->call('deferChangeRequest')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    $change->refresh();
    expect($change->status)->toBe('deferred')
        ->and($change->decision)->toBe('deferred');
});

it('cancels an under_review change request from the webapp', function () {
    $change = ($this->makeChange)('under_review');

    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.show', ['changeRequest' => $change])
        ->call('cancelChangeRequest')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($change->fresh()->status)->toBe('cancelled');
});

it('marks an approved change request implemented from the webapp', function () {
    $change = ($this->makeChange)('approved', 'approved');

    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.show', ['changeRequest' => $change])
        ->assertSee('Mark implemented')
        ->call('markChangeRequestImplemented')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($change->fresh()->status)->toBe('implemented')
        ->and(ChangeApprovalEvent::query()->sole()->to_status)->toBe('implemented');
});

it('cancels a deferred change request from the webapp', function () {
    $change = ($this->makeChange)('deferred', 'deferred');

    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.show', ['changeRequest' => $change])
        ->assertSee('Cancel')
        ->call('cancelChangeRequest')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($change->fresh()->status)->toBe('cancelled');
});

it('rejects an illegal transition from the webapp and warns the user', function () {
    $change = ($this->makeChange)('approved', 'approved');

    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.show', ['changeRequest' => $change])
        ->call('submitChangeRequest')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($change->fresh()->status)->toBe('approved')
        ->and(ChangeApprovalEvent::count())->toBe(0);
});

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
