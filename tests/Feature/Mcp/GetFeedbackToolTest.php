<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Feedback\GetFeedback;
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
        'summary' => 'Generic summary',
        'body' => 'Generic body',
    ], $attributes));
});

it('returns the full feedback payload including the body', function () {
    $feedback = ($this->makeFeedback)([
        'tool_name' => 'upsert-risk',
        'summary' => 'Pagination is unclear',
        'body' => 'The offset behaviour is not documented and trips up callers.',
    ]);

    ReadonlyServer::tool(GetFeedback::class, ['feedback_id' => $feedback->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($feedback) {
            $json->where('id', $feedback->id)
                ->where('category', 'difficulty')
                ->where('status', 'new')
                ->where('tool_name', 'upsert-risk')
                ->where('summary', 'Pagination is unclear')
                ->where('body', 'The offset behaviour is not documented and trips up callers.')
                ->etc();
        });
});

it('errors on an unknown feedback id', function () {
    ReadonlyServer::tool(GetFeedback::class, ['feedback_id' => 'does-not-exist'])
        ->assertHasErrors();
});

it('does not return feedback from another workspace', function () {
    $other = User::factory()->create();
    $otherFeedback = ToolFeedback::create([
        'workspace_id' => $other->active_workspace_id,
        'user_id' => $other->id,
        'category' => 'bug',
        'status' => 'new',
        'summary' => 'Theirs',
        'body' => 'Their feedback',
    ]);

    ReadonlyServer::tool(GetFeedback::class, ['feedback_id' => $otherFeedback->id])
        ->assertHasErrors();
});
