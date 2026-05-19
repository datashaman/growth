<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Feedback\CommentFeedback;
use App\Models\FeedbackComment;
use App\Models\ToolFeedback;
use App\Models\User;
use App\Notifications\FeedbackCommented;
use App\Support\CapabilitySurface;
use App\Support\SurfaceContext;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->actor = User::factory()->create();
    Passport::actingAs($this->actor, ['mcp:use']);

    $this->makeFeedback = fn (?User $filer = null, ?string $workspaceId = null): ToolFeedback => ToolFeedback::create([
        'workspace_id' => $workspaceId ?? $this->actor->active_workspace_id,
        'user_id' => $filer?->id,
        'category' => 'difficulty',
        'status' => 'new',
        'summary' => 'Generic summary',
        'body' => 'Generic body',
    ]);
});

it('appends a comment attributed to its author', function () {
    $feedback = ($this->makeFeedback)();

    ReadonlyServer::tool(CommentFeedback::class, ['feedback_id' => $feedback->id, 'body' => 'A follow-up question'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('created', true)->etc());

    $comment = $feedback->comments()->sole();

    expect($comment->body)->toBe('A follow-up question')
        ->and($comment->user_id)->toBe($this->actor->id);
});

it('records the acting role on the comment', function () {
    $feedback = ($this->makeFeedback)();
    app(SurfaceContext::class)->set(CapabilitySurface::Governance);

    GovernanceServer::tool(CommentFeedback::class, ['feedback_id' => $feedback->id, 'body' => 'Triage note'])
        ->assertOk();

    expect($feedback->comments()->sole()->acting_surface)->toBe('governance');
});

it('rejects an unknown feedback id', function () {
    ReadonlyServer::tool(CommentFeedback::class, ['feedback_id' => 'missing', 'body' => 'Hello'])
        ->assertHasErrors();
});

it('does not comment on feedback in another workspace', function () {
    $other = User::factory()->create();
    $feedback = ($this->makeFeedback)(workspaceId: $other->active_workspace_id);

    ReadonlyServer::tool(CommentFeedback::class, ['feedback_id' => $feedback->id, 'body' => 'Hello'])
        ->assertHasErrors();

    expect($feedback->comments()->count())->toBe(0);
});

it('notifies the filer but not the commenting author', function () {
    $filer = User::factory()->create();
    $feedback = ($this->makeFeedback)($filer);

    Notification::fake();

    ReadonlyServer::tool(CommentFeedback::class, ['feedback_id' => $feedback->id, 'body' => 'Looking into it'])
        ->assertOk();

    Notification::assertSentTo($filer, FeedbackCommented::class);
    Notification::assertNotSentTo($this->actor, FeedbackCommented::class);
});

it('notifies a prior commenter', function () {
    $filer = User::factory()->create();
    $priorCommenter = User::factory()->create();
    $feedback = ($this->makeFeedback)($filer);
    FeedbackComment::factory()->create([
        'tool_feedback_id' => $feedback->id,
        'user_id' => $priorCommenter->id,
    ]);

    Notification::fake();

    ReadonlyServer::tool(CommentFeedback::class, ['feedback_id' => $feedback->id, 'body' => 'Adding detail'])
        ->assertOk();

    Notification::assertSentTo($filer, FeedbackCommented::class);
    Notification::assertSentTo($priorCommenter, FeedbackCommented::class);
});

it('notifies no one when commenting on agent-filed feedback', function () {
    $feedback = ($this->makeFeedback)();

    Notification::fake();

    ReadonlyServer::tool(CommentFeedback::class, ['feedback_id' => $feedback->id, 'body' => 'First reply'])
        ->assertOk();

    Notification::assertNothingSent();
});

it('notifies no one when the author is the only participant', function () {
    $feedback = ($this->makeFeedback)($this->actor);

    Notification::fake();

    ReadonlyServer::tool(CommentFeedback::class, ['feedback_id' => $feedback->id, 'body' => 'A note to self'])
        ->assertOk();

    Notification::assertNothingSent();
});
