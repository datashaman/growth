<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Tools\Reviews\CancelReview;
use App\Mcp\Tools\Reviews\CloseReview;
use App\Mcp\Tools\Reviews\HoldReview;
use App\Mcp\Tools\Reviews\StartReview;
use App\Mcp\Tools\Reviews\UpsertReview;
use App\Models\Project;
use App\Models\Review;
use App\Models\ReviewDecisionEvent;
use App\Models\StatusTransition;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Review transitions',
        'rigor_level' => 2,
    ]);

    $this->makeReview = fn (string $status): Review => Review::create([
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'Architecture review',
        'status' => $status,
    ]);
});

it('starts a planned review and records a decision event', function () {
    $review = ($this->makeReview)('planned');

    GovernanceServer::tool(StartReview::class, ['review_id' => $review->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($review) {
            $json->where('review_id', $review->id)
                ->where('from_status', 'planned')
                ->where('to_status', 'in_progress')
                ->etc();
        });

    expect($review->fresh()->status)->toBe('in_progress');

    $event = ReviewDecisionEvent::query()->sole();
    expect($event->from_status)->toBe('planned')
        ->and($event->to_status)->toBe('in_progress')
        ->and($event->recorded_by_user_id)->toBe($this->user->id)
        ->and($event->recorded_at)->not->toBeNull()
        ->and($event->review->is($review))->toBeTrue();

    expect(StatusTransition::count())->toBe(0);
});

it('rejects starting a review that is not planned', function () {
    $review = ($this->makeReview)('held');

    GovernanceServer::tool(StartReview::class, ['review_id' => $review->id])
        ->assertHasErrors(['Cannot start a review that is held.']);

    expect($review->fresh()->status)->toBe('held');
    expect(ReviewDecisionEvent::count())->toBe(0);
});

it('holds an in_progress review and records the rationale', function () {
    $review = ($this->makeReview)('in_progress');

    GovernanceServer::tool(HoldReview::class, ['review_id' => $review->id, 'rationale' => 'Session complete'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'in_progress')
                ->where('to_status', 'held')
                ->etc();
        });

    expect($review->fresh()->status)->toBe('held');
    expect(ReviewDecisionEvent::query()->sole()->rationale)->toBe('Session complete');
});

it('rejects holding a review that is not in_progress', function () {
    $review = ($this->makeReview)('planned');

    GovernanceServer::tool(HoldReview::class, ['review_id' => $review->id])
        ->assertHasErrors(['Cannot hold a review that is planned.']);

    expect($review->fresh()->status)->toBe('planned');
});

it('closes a held review', function () {
    $review = ($this->makeReview)('held');

    GovernanceServer::tool(CloseReview::class, ['review_id' => $review->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'held')
                ->where('to_status', 'closed')
                ->etc();
        });

    expect($review->fresh()->status)->toBe('closed');
});

it('rejects closing a review that is not held', function () {
    $review = ($this->makeReview)('in_progress');

    GovernanceServer::tool(CloseReview::class, ['review_id' => $review->id])
        ->assertHasErrors(['Cannot close a review that is in progress.']);

    expect($review->fresh()->status)->toBe('in_progress');
});

it('cancels a planned review', function () {
    $review = ($this->makeReview)('planned');

    GovernanceServer::tool(CancelReview::class, ['review_id' => $review->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'planned')
                ->where('to_status', 'cancelled')
                ->etc();
        });

    expect($review->fresh()->status)->toBe('cancelled');
});

it('cancels an in_progress review', function () {
    $review = ($this->makeReview)('in_progress');

    GovernanceServer::tool(CancelReview::class, ['review_id' => $review->id])->assertOk();

    expect($review->fresh()->status)->toBe('cancelled');
});

it('rejects cancelling a review that is held', function () {
    $review = ($this->makeReview)('held');

    GovernanceServer::tool(CancelReview::class, ['review_id' => $review->id])
        ->assertHasErrors(['Cannot cancel a review that is held.']);

    expect($review->fresh()->status)->toBe('held');
});

it('rejects status passed to upsert-review with a pointer to the transition tools', function () {
    GovernanceServer::tool(UpsertReview::class, [
        'project_id' => $this->project->id,
        'type' => 'technical_review',
        'title' => 'No status here',
        'status' => 'held',
    ])->assertHasErrors([
        'Review status is not set here. Use the start-review, hold-review, close-review, and cancel-review tools to move status through validated transitions.',
    ]);

    expect(Review::where('title', 'No status here')->exists())->toBeFalse();
});

it('creates a review as planned through upsert-review', function () {
    GovernanceServer::tool(UpsertReview::class, [
        'project_id' => $this->project->id,
        'type' => 'inspection',
        'title' => 'Fresh review',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('status', 'planned')->etc();
        });

    expect(Review::where('title', 'Fresh review')->sole()->status)->toBe('planned');
});

it('rejects a transition on a review the user does not own', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignReview = Review::create([
        'project_id' => $strangerProject->id,
        'type' => 'audit',
        'title' => 'Off limits',
        'status' => 'planned',
    ]);

    GovernanceServer::tool(StartReview::class, ['review_id' => $foreignReview->id])
        ->assertHasErrors();

    expect($foreignReview->fresh()->status)->toBe('planned');
});
