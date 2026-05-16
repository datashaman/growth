<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Tools\Reviews\AcceptFinding;
use App\Mcp\Tools\Reviews\CloseFinding;
use App\Mcp\Tools\Reviews\DispositionFinding;
use App\Mcp\Tools\Reviews\ReopenFinding;
use App\Mcp\Tools\Reviews\ResolveFinding;
use App\Mcp\Tools\Reviews\UpsertReviewFinding;
use App\Models\Project;
use App\Models\Review;
use App\Models\ReviewFinding;
use App\Models\StatusTransition;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

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

it('dispositions an open finding and records a transition', function () {
    $finding = ($this->makeFinding)('open');

    GovernanceServer::tool(DispositionFinding::class, ['review_finding_id' => $finding->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($finding) {
            $json->where('review_finding_id', $finding->id)
                ->where('from_status', 'open')
                ->where('to_status', 'dispositioned')
                ->etc();
        });

    expect($finding->fresh()->status)->toBe('dispositioned');

    $transition = StatusTransition::query()->sole();
    expect($transition->from_status)->toBe('open')
        ->and($transition->to_status)->toBe('dispositioned')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($transition->transitionable->is($finding))->toBeTrue();
});

it('rejects dispositioning a finding that is not open', function () {
    $finding = ($this->makeFinding)('resolved');

    GovernanceServer::tool(DispositionFinding::class, ['review_finding_id' => $finding->id])
        ->assertHasErrors(['Cannot disposition a review finding that is resolved.']);

    expect($finding->fresh()->status)->toBe('resolved');
    expect(StatusTransition::count())->toBe(0);
});

it('resolves a dispositioned finding', function () {
    $finding = ($this->makeFinding)('dispositioned');

    GovernanceServer::tool(ResolveFinding::class, ['review_finding_id' => $finding->id, 'reason' => 'Fixed'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'dispositioned')
                ->where('to_status', 'resolved')
                ->etc();
        });

    expect($finding->fresh()->status)->toBe('resolved');
    expect(StatusTransition::query()->sole()->reason)->toBe('Fixed');
});

it('rejects resolving a finding that is not dispositioned', function () {
    $finding = ($this->makeFinding)('open');

    GovernanceServer::tool(ResolveFinding::class, ['review_finding_id' => $finding->id])
        ->assertHasErrors(['Cannot resolve a review finding that is open.']);

    expect($finding->fresh()->status)->toBe('open');
});

it('accepts an open finding', function () {
    $finding = ($this->makeFinding)('open');

    GovernanceServer::tool(AcceptFinding::class, ['review_finding_id' => $finding->id])->assertOk();

    expect($finding->fresh()->status)->toBe('accepted');
});

it('accepts a dispositioned finding', function () {
    $finding = ($this->makeFinding)('dispositioned');

    GovernanceServer::tool(AcceptFinding::class, ['review_finding_id' => $finding->id])->assertOk();

    expect($finding->fresh()->status)->toBe('accepted');
});

it('rejects accepting a finding that is closed', function () {
    $finding = ($this->makeFinding)('closed');

    GovernanceServer::tool(AcceptFinding::class, ['review_finding_id' => $finding->id])
        ->assertHasErrors(['Cannot accept a review finding that is closed.']);

    expect($finding->fresh()->status)->toBe('closed');
});

it('closes a resolved finding', function () {
    $finding = ($this->makeFinding)('resolved');

    GovernanceServer::tool(CloseFinding::class, ['review_finding_id' => $finding->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'resolved')
                ->where('to_status', 'closed')
                ->etc();
        });

    expect($finding->fresh()->status)->toBe('closed');
});

it('closes an accepted finding', function () {
    $finding = ($this->makeFinding)('accepted');

    GovernanceServer::tool(CloseFinding::class, ['review_finding_id' => $finding->id])->assertOk();

    expect($finding->fresh()->status)->toBe('closed');
});

it('rejects closing a finding that is open', function () {
    $finding = ($this->makeFinding)('open');

    GovernanceServer::tool(CloseFinding::class, ['review_finding_id' => $finding->id])
        ->assertHasErrors(['Cannot close a review finding that is open.']);

    expect($finding->fresh()->status)->toBe('open');
});

it('reopens a closed finding', function () {
    $finding = ($this->makeFinding)('closed');

    GovernanceServer::tool(ReopenFinding::class, ['review_finding_id' => $finding->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'closed')
                ->where('to_status', 'open')
                ->etc();
        });

    expect($finding->fresh()->status)->toBe('open');
});

it('rejects reopening a finding that is not closed', function () {
    $finding = ($this->makeFinding)('resolved');

    GovernanceServer::tool(ReopenFinding::class, ['review_finding_id' => $finding->id])
        ->assertHasErrors(['Cannot reopen a review finding that is resolved.']);

    expect($finding->fresh()->status)->toBe('resolved');
});

it('rejects status passed to upsert-review-finding with a pointer to the transition tools', function () {
    GovernanceServer::tool(UpsertReviewFinding::class, [
        'review_id' => $this->review->id,
        'title' => 'No status here',
        'status' => 'resolved',
    ])->assertHasErrors([
        'Review finding status is not set here. Use the disposition-finding, resolve-finding, accept-finding, close-finding, and reopen-finding tools to move status through validated transitions.',
    ]);

    expect(ReviewFinding::where('title', 'No status here')->exists())->toBeFalse();
});

it('creates a finding as open through upsert-review-finding', function () {
    GovernanceServer::tool(UpsertReviewFinding::class, [
        'review_id' => $this->review->id,
        'title' => 'Fresh finding',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('status', 'open')->etc();
        });

    expect(ReviewFinding::where('title', 'Fresh finding')->sole()->status)->toBe('open');
});

it('rejects a transition on a finding the user does not own', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignReview = Review::create([
        'project_id' => $strangerProject->id,
        'type' => 'audit',
        'title' => 'Foreign review',
        'status' => 'in_progress',
    ]);
    $foreignFinding = ReviewFinding::create([
        'project_id' => $strangerProject->id,
        'review_id' => $foreignReview->id,
        'title' => 'Off limits',
        'severity' => 'low',
        'status' => 'open',
    ]);

    GovernanceServer::tool(DispositionFinding::class, ['review_finding_id' => $foreignFinding->id])
        ->assertHasErrors();

    expect($foreignFinding->fresh()->status)->toBe('open');
});
