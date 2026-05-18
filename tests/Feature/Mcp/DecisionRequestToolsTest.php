<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Decisions\AnswerDecisionRequest;
use App\Mcp\Tools\Decisions\CancelDecisionRequest;
use App\Mcp\Tools\Decisions\CreateDecisionRequest;
use App\Mcp\Tools\Decisions\ListDecisionQueue;
use App\Models\ChangeRequest;
use App\Models\DecisionRequest;
use App\Models\DecisionRequestOption;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DecisionRequestAnswered;
use App\Notifications\DecisionRequestRaised;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->actor = User::factory()->create();
    Passport::actingAs($this->actor, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->actor->active_workspace_id,
        'name' => 'Festival Market',
        'rigor_level' => 2,
    ]);

    $this->role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Product Owner',
    ]);

    $this->makeRequest = fn (array $attributes = []): DecisionRequest => DecisionRequest::factory()->create(array_merge([
        'project_id' => $this->project->id,
        'target_role_id' => $this->role->id,
    ], $attributes));
});

it('creates a decision request with options targeting a role', function () {
    ReadonlyServer::tool(CreateDecisionRequest::class, [
        'target_role_id' => $this->role->id,
        'question' => 'Which venue should we book?',
        'options' => ['Stadium', 'Park'],
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('created', true)
            ->where('status', 'open')
            ->etc());

    $decisionRequest = DecisionRequest::query()->sole();

    expect($decisionRequest->question)->toBe('Which venue should we book?')
        ->and($decisionRequest->requester_user_id)->toBe($this->actor->id)
        ->and($decisionRequest->target_role_id)->toBe($this->role->id)
        ->and($decisionRequest->status)->toBe('open')
        ->and($decisionRequest->options->pluck('label')->all())->toBe(['Stadium', 'Park']);
});

it('rejects a decision request with fewer than two options', function () {
    ReadonlyServer::tool(CreateDecisionRequest::class, [
        'target_role_id' => $this->role->id,
        'question' => 'Which venue?',
        'options' => ['Stadium'],
    ])->assertHasErrors();

    expect(DecisionRequest::query()->count())->toBe(0);
});

it('does not create a decision request for a role in another workspace', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Elsewhere',
        'rigor_level' => 2,
    ]);
    $otherRole = Role::create(['project_id' => $otherProject->id, 'name' => 'Owner']);

    ReadonlyServer::tool(CreateDecisionRequest::class, [
        'target_role_id' => $otherRole->id,
        'question' => 'Which venue?',
        'options' => ['Stadium', 'Park'],
    ])->assertHasErrors();
});

it('notifies role assignees when a decision request is raised', function () {
    $assignee = User::factory()->create();
    $this->role->users()->attach([$assignee->id, $this->actor->id]);

    Notification::fake();

    ReadonlyServer::tool(CreateDecisionRequest::class, [
        'target_role_id' => $this->role->id,
        'question' => 'Which venue?',
        'options' => ['Stadium', 'Park'],
    ])->assertOk();

    Notification::assertSentTo($assignee, DecisionRequestRaised::class);
    Notification::assertNotSentTo($this->actor, DecisionRequestRaised::class);
});

it('links a decision request to a polymorphic subject', function () {
    $change = ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Move the main stage',
        'category' => 'scope',
        'priority' => 'medium',
        'status' => 'proposed',
    ]);

    ReadonlyServer::tool(CreateDecisionRequest::class, [
        'target_role_id' => $this->role->id,
        'question' => 'Approve the stage move?',
        'options' => ['Yes', 'No'],
        'subject_type' => 'change_request',
        'subject_id' => $change->id,
    ])->assertOk();

    $decisionRequest = DecisionRequest::query()->sole();

    expect($decisionRequest->subjectable_type)->toBe('change_request')
        ->and($decisionRequest->subjectable_id)->toBe($change->id)
        ->and($decisionRequest->subjectable->is($change))->toBeTrue();
});

it('rejects a decision request with an unknown subject type', function () {
    ReadonlyServer::tool(CreateDecisionRequest::class, [
        'target_role_id' => $this->role->id,
        'question' => 'Which venue?',
        'options' => ['Stadium', 'Park'],
        'subject_type' => 'not_a_morph_key',
        'subject_id' => 'whatever',
    ])->assertHasErrors();

    expect(DecisionRequest::query()->count())->toBe(0);
});

it('rejects a decision request linked to a nonexistent subject', function () {
    ReadonlyServer::tool(CreateDecisionRequest::class, [
        'target_role_id' => $this->role->id,
        'question' => 'Which venue?',
        'options' => ['Stadium', 'Park'],
        'subject_type' => 'change_request',
        'subject_id' => 'nonexistent-ulid',
    ])->assertHasErrors();

    expect(DecisionRequest::query()->count())->toBe(0);
});

it('rejects a subject id supplied without a subject type', function () {
    ReadonlyServer::tool(CreateDecisionRequest::class, [
        'target_role_id' => $this->role->id,
        'question' => 'Which venue?',
        'options' => ['Stadium', 'Park'],
        'subject_id' => 'orphan-ulid',
    ])->assertHasErrors();

    expect(DecisionRequest::query()->count())->toBe(0);
});

it('lists open decision requests for roles the caller is assigned to', function () {
    $this->actor->roles()->attach($this->role);
    $decisionRequest = ($this->makeRequest)();

    ReadonlyServer::tool(ListDecisionQueue::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('count', 1)
            ->where('status', 'open')
            ->where('decision_requests.0.id', $decisionRequest->id)
            ->etc());
});

it('omits decision requests for roles the caller does not hold', function () {
    ($this->makeRequest)();

    ReadonlyServer::tool(ListDecisionQueue::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('count', 0)->etc());
});

it('lists a queue for an explicit role id even when the caller is not assigned', function () {
    $decisionRequest = ($this->makeRequest)();

    ReadonlyServer::tool(ListDecisionQueue::class, ['role_id' => $this->role->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('count', 1)
            ->where('decision_requests.0.id', $decisionRequest->id)
            ->etc());
});

it('filters the decision queue by status', function () {
    $this->actor->roles()->attach($this->role);
    ($this->makeRequest)(['status' => 'open']);
    $answered = ($this->makeRequest)(['status' => 'answered']);

    ReadonlyServer::tool(ListDecisionQueue::class, ['status' => 'answered'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('count', 1)
            ->where('status', 'answered')
            ->where('decision_requests.0.id', $answered->id)
            ->etc());
});

it('answers a decision request and notifies the requester', function () {
    $requester = User::factory()->create();
    $this->role->users()->attach($this->actor);
    $decisionRequest = ($this->makeRequest)(['requester_user_id' => $requester->id]);
    $stadium = DecisionRequestOption::factory()->for($decisionRequest)->create(['label' => 'Stadium', 'position' => 0]);

    Notification::fake();

    ReadonlyServer::tool(AnswerDecisionRequest::class, [
        'decision_request_id' => $decisionRequest->id,
        'option_id' => $stadium->id,
        'rationale' => 'Bigger capacity',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('status', 'answered')
            ->where('answered', true)
            ->etc());

    $decisionRequest->refresh();

    expect($decisionRequest->status)->toBe('answered')
        ->and($decisionRequest->chosen_option_id)->toBe($stadium->id)
        ->and($decisionRequest->answer_rationale)->toBe('Bigger capacity')
        ->and($decisionRequest->answered_by_user_id)->toBe($this->actor->id);

    Notification::assertSentTo($requester, DecisionRequestAnswered::class);
});

it('records an auditable status transition when answered', function () {
    $this->role->users()->attach($this->actor);
    $decisionRequest = ($this->makeRequest)(['requester_user_id' => $this->actor->id]);
    $option = DecisionRequestOption::factory()->for($decisionRequest)->create(['position' => 0]);

    ReadonlyServer::tool(AnswerDecisionRequest::class, [
        'decision_request_id' => $decisionRequest->id,
        'option_id' => $option->id,
        'rationale' => 'Settled',
    ])->assertOk();

    $transition = $decisionRequest->statusTransitions()->sole();

    expect($transition->from_status)->toBe('open')
        ->and($transition->to_status)->toBe('answered')
        ->and($transition->reason)->toBe('Settled')
        ->and($transition->transitioned_by_user_id)->toBe($this->actor->id);
});

it('rejects an answer from a user not assigned to the target role', function () {
    $decisionRequest = ($this->makeRequest)();
    $option = DecisionRequestOption::factory()->for($decisionRequest)->create(['position' => 0]);

    ReadonlyServer::tool(AnswerDecisionRequest::class, [
        'decision_request_id' => $decisionRequest->id,
        'option_id' => $option->id,
        'rationale' => 'Trying anyway',
    ])->assertHasErrors();

    expect($decisionRequest->fresh()->status)->toBe('open');
});

it('rejects an option that belongs to another decision request', function () {
    $this->role->users()->attach($this->actor);
    $decisionRequest = ($this->makeRequest)();
    $otherRequest = ($this->makeRequest)();
    $foreignOption = DecisionRequestOption::factory()->for($otherRequest)->create(['position' => 0]);

    ReadonlyServer::tool(AnswerDecisionRequest::class, [
        'decision_request_id' => $decisionRequest->id,
        'option_id' => $foreignOption->id,
        'rationale' => 'Wrong option',
    ])->assertHasErrors();

    expect($decisionRequest->fresh()->status)->toBe('open');
});

it('cancels an open decision request raised by the caller', function () {
    $decisionRequest = ($this->makeRequest)(['requester_user_id' => $this->actor->id]);

    ReadonlyServer::tool(CancelDecisionRequest::class, ['decision_request_id' => $decisionRequest->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('status', 'cancelled')
            ->where('cancelled', true)
            ->etc());

    expect($decisionRequest->fresh()->status)->toBe('cancelled');
});

it('rejects cancelling a decision request the caller did not raise', function () {
    $other = User::factory()->create();
    $decisionRequest = ($this->makeRequest)(['requester_user_id' => $other->id]);

    ReadonlyServer::tool(CancelDecisionRequest::class, ['decision_request_id' => $decisionRequest->id])
        ->assertHasErrors();

    expect($decisionRequest->fresh()->status)->toBe('open');
});
