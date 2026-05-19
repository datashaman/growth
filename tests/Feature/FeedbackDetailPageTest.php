<?php

use App\Growth\Transitions\TriageFeedback;
use App\Models\ToolFeedback;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->makeFeedback = fn (array $attributes = []): ToolFeedback => ToolFeedback::create(array_merge([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'category' => 'suggestion',
        'status' => 'new',
        'tool_name' => 'list-risks',
        'summary' => 'list-risks should accept a project slug',
        'body' => 'Passing a slug instead of an id would be friendlier.',
    ], $attributes));
});

it('serves the feedback detail page', function () {
    $feedback = ($this->makeFeedback)();

    $this->actingAs($this->user)
        ->get(route('feedback.show', $feedback))
        ->assertOk();
});

it('renders the full feedback record', function () {
    $feedback = ($this->makeFeedback)();

    $this->actingAs($this->user)
        ->get(route('feedback.show', $feedback))
        ->assertSee('list-risks should accept a project slug')
        ->assertSee('Passing a slug instead of an id would be friendlier.')
        ->assertSee('list-risks')
        ->assertSee('Filed by')
        ->assertSee($this->user->name);
});

it('renders the status-transition history', function () {
    $feedback = ($this->makeFeedback)();
    (new TriageFeedback)->apply($feedback, $this->user, 'Looking into it');

    $this->actingAs($this->user)
        ->get(route('feedback.show', $feedback))
        ->assertSee('triaged')
        ->assertSee('Looking into it');
});

it('returns 404 for feedback in another workspace', function () {
    $other = User::factory()->create();
    $feedback = ($this->makeFeedback)([
        'workspace_id' => $other->active_workspace_id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('feedback.show', $feedback))
        ->assertNotFound();
});

it('links each inbox row to its detail page', function () {
    $feedback = ($this->makeFeedback)();

    $this->actingAs($this->user)
        ->get(route('feedback'))
        ->assertSee(route('feedback.show', $feedback));
});

it('renders the comment thread', function () {
    $feedback = ($this->makeFeedback)();
    $feedback->comments()->create([
        'user_id' => $this->user->id,
        'body' => 'A follow-up question on this feedback',
    ]);

    $this->actingAs($this->user)
        ->get(route('feedback.show', $feedback))
        ->assertSee('Comments')
        ->assertSee('A follow-up question on this feedback');
});

it('shows the adopted role on a comment', function () {
    $feedback = ($this->makeFeedback)();
    $feedback->comments()->create([
        'user_id' => $this->user->id,
        'acting_role_name' => 'Engineering Lead',
        'body' => 'A triage note',
    ]);

    $this->actingAs($this->user)
        ->get(route('feedback.show', $feedback))
        ->assertSee('Engineering Lead');
});

it('shows the adopted role on a status transition', function () {
    $feedback = ($this->makeFeedback)();
    $feedback->statusTransitions()->create([
        'transitioned_by' => $this->user->id,
        'acting_role_name' => 'Engineering Lead',
        'from_status' => 'new',
        'to_status' => 'triaged',
        'transitioned_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('feedback.show', $feedback))
        ->assertSee('Engineering Lead');
});
