<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Feedback\UpdateFeedback;
use App\Models\ToolFeedback;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->makeFeedback = fn (array $attributes = []): ToolFeedback => ToolFeedback::create(array_merge([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'category' => 'difficulty',
        'status' => 'new',
        'tool_name' => 'send-feedback',
        'summary' => 'Original summary',
        'body' => 'Original body',
    ], $attributes));
});

it('updates editable feedback fields without changing the feedback id', function () {
    $feedback = ($this->makeFeedback)();

    ReadonlyServer::tool(UpdateFeedback::class, [
        'feedback_id' => $feedback->id,
        'category' => 'bug',
        'summary' => 'Corrected summary',
        'body' => 'Corrected body',
        'tool_name' => 'get_feedback',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($feedback) {
            $json->where('id', $feedback->id)
                ->where('updated', true)
                ->where('category', 'bug')
                ->where('tool_name', 'get-feedback')
                ->where('summary', 'Corrected summary')
                ->where('body', 'Corrected body')
                ->etc();
        });

    expect($feedback->fresh())
        ->id->toBe($feedback->id)
        ->category->toBe('bug')
        ->tool_name->toBe('get-feedback')
        ->summary->toBe('Corrected summary')
        ->body->toBe('Corrected body');
});

it('preserves comments and status transitions while recording an audit comment', function () {
    $feedback = ($this->makeFeedback)(['status' => 'triaged']);
    $existingComment = $feedback->comments()->create([
        'user_id' => $this->user->id,
        'body' => 'Existing thread note',
    ]);
    $transition = $feedback->statusTransitions()->create([
        'from_status' => 'new',
        'to_status' => 'triaged',
        'transitioned_by_user_id' => $this->user->id,
        'transitioned_at' => now(),
    ]);

    ReadonlyServer::tool(UpdateFeedback::class, [
        'feedback_id' => $feedback->id,
        'summary' => 'Corrected summary',
    ])->assertOk();

    expect($feedback->fresh()->status)->toBe('triaged')
        ->and($feedback->comments()->whereKey($existingComment->id)->exists())->toBeTrue()
        ->and($feedback->statusTransitions()->whereKey($transition->id)->exists())->toBeTrue()
        ->and($feedback->comments()->count())->toBe(2);

    $auditComment = $feedback->comments()->latest('created_at')->first();
    expect($auditComment->body)->toBe('Updated feedback fields: summary.')
        ->and($auditComment->user_id)->toBe($this->user->id);
});

it('reports unchanged updates without creating an audit comment', function () {
    $feedback = ($this->makeFeedback)();

    ReadonlyServer::tool(UpdateFeedback::class, [
        'feedback_id' => $feedback->id,
        'summary' => 'Original summary',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('updated', false)
                ->where('changed_fields', [])
                ->etc();
        });

    expect($feedback->comments()->count())->toBe(0);
});

it('requires at least one editable field and rejects status edits', function () {
    $feedback = ($this->makeFeedback)();

    ReadonlyServer::tool(UpdateFeedback::class, [
        'feedback_id' => $feedback->id,
    ])->assertHasErrors();

    ReadonlyServer::tool(UpdateFeedback::class, [
        'feedback_id' => $feedback->id,
        'status' => 'resolved',
    ])->assertHasErrors();

    expect($feedback->fresh()->status)->toBe('new');
});

it('does not update feedback from another workspace', function () {
    $other = User::factory()->create();
    $feedback = ToolFeedback::create([
        'workspace_id' => $other->active_workspace_id,
        'user_id' => $other->id,
        'category' => 'bug',
        'status' => 'new',
        'summary' => 'Foreign summary',
        'body' => 'Foreign body',
    ]);

    ReadonlyServer::tool(UpdateFeedback::class, [
        'feedback_id' => $feedback->id,
        'summary' => 'Changed',
    ])->assertHasErrors();

    expect($feedback->fresh()->summary)->toBe('Foreign summary');
});
