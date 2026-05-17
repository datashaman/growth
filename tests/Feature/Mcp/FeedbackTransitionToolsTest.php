<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Feedback\ReopenFeedback;
use App\Mcp\Tools\Feedback\ResolveFeedback;
use App\Mcp\Tools\Feedback\TriageFeedback;
use App\Models\StatusTransition;
use App\Models\ToolFeedback;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->makeFeedback = fn (string $status, ?string $workspaceId = null): ToolFeedback => ToolFeedback::create([
        'workspace_id' => $workspaceId ?? $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'category' => 'bug',
        'status' => $status,
        'summary' => 'list-risks returns a 500',
        'body' => 'Calling list-risks without a project_id throws.',
    ]);
});

it('triages new feedback and records a transition', function () {
    $feedback = ($this->makeFeedback)('new');

    PlanningServer::tool(TriageFeedback::class, ['feedback_id' => $feedback->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'new')->where('to_status', 'triaged')->etc();
        });

    expect($feedback->fresh()->status)->toBe('triaged');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('triaged')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($transition->transitionable->is($feedback))->toBeTrue();
});

it('resolves feedback from new or triaged', function () {
    $fromNew = ($this->makeFeedback)('new');
    $fromTriaged = ($this->makeFeedback)('triaged');

    PlanningServer::tool(ResolveFeedback::class, ['feedback_id' => $fromNew->id])->assertOk();
    PlanningServer::tool(ResolveFeedback::class, ['feedback_id' => $fromTriaged->id])->assertOk();

    expect($fromNew->fresh()->status)->toBe('resolved')
        ->and($fromTriaged->fresh()->status)->toBe('resolved');
});

it('reopens triaged or resolved feedback back to new', function () {
    $feedback = ($this->makeFeedback)('resolved');

    PlanningServer::tool(ReopenFeedback::class, ['feedback_id' => $feedback->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'resolved')->where('to_status', 'new')->etc();
        });

    expect($feedback->fresh()->status)->toBe('new');
});

it('records the optional reason on the transition', function () {
    $feedback = ($this->makeFeedback)('new');

    PlanningServer::tool(TriageFeedback::class, [
        'feedback_id' => $feedback->id,
        'reason' => 'Reproduced locally.',
    ])->assertOk();

    expect(StatusTransition::query()->sole()->reason)->toBe('Reproduced locally.');
});

it('rejects an illegal feedback transition without writing a row', function () {
    $feedback = ($this->makeFeedback)('new');

    PlanningServer::tool(ReopenFeedback::class, ['feedback_id' => $feedback->id])
        ->assertHasErrors(['Cannot reopen a feedback that is new.']);

    expect($feedback->fresh()->status)->toBe('new')
        ->and(StatusTransition::count())->toBe(0);
});

it('rejects triaging feedback that is already triaged', function () {
    $feedback = ($this->makeFeedback)('triaged');

    PlanningServer::tool(TriageFeedback::class, ['feedback_id' => $feedback->id])
        ->assertHasErrors(['Cannot triage a feedback that is triaged.']);

    expect(StatusTransition::count())->toBe(0);
});

it('rejects resolving feedback that is already resolved', function () {
    $feedback = ($this->makeFeedback)('resolved');

    PlanningServer::tool(ResolveFeedback::class, ['feedback_id' => $feedback->id])
        ->assertHasErrors(['Cannot resolve a feedback that is resolved.']);

    expect(StatusTransition::count())->toBe(0);
});

it('does not transition feedback from another workspace', function () {
    $stranger = User::factory()->create();
    $foreign = ($this->makeFeedback)('new', $stranger->active_workspace_id);

    PlanningServer::tool(TriageFeedback::class, ['feedback_id' => $foreign->id])
        ->assertHasErrors(['No feedback with that id exists in the active workspace.']);

    expect($foreign->fresh()->status)->toBe('new')
        ->and(StatusTransition::count())->toBe(0);
});
