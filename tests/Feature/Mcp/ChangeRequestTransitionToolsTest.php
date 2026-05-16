<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Tools\Changes\ApproveChangeRequest;
use App\Mcp\Tools\Changes\CancelChangeRequest;
use App\Mcp\Tools\Changes\DeferChangeRequest;
use App\Mcp\Tools\Changes\MarkChangeRequestImplemented;
use App\Mcp\Tools\Changes\RejectChangeRequest;
use App\Mcp\Tools\Changes\SubmitChangeRequest;
use App\Mcp\Tools\Changes\UpsertChangeRequest;
use App\Models\ChangeApprovalEvent;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Transitions',
        'rigor_level' => 2,
    ]);

    $this->makeChange = fn (string $status, ?string $decision = null): ChangeRequest => ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Change',
        'category' => 'scope',
        'status' => $status,
        'decision' => $decision,
    ]);
});

it('submits a proposed change request and records an approval event', function () {
    $change = ($this->makeChange)('proposed');

    GovernanceServer::tool(SubmitChangeRequest::class, ['change_request_id' => $change->id, 'reason' => 'Ready'])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($change) {
            $json->where('change_request_id', $change->id)
                ->where('from_status', 'proposed')
                ->where('to_status', 'under_review')
                ->etc();
        });

    expect($change->fresh()->status)->toBe('under_review');

    $event = ChangeApprovalEvent::query()->sole();
    expect($event->from_status)->toBe('proposed')
        ->and($event->to_status)->toBe('under_review')
        ->and($event->to_decision)->toBeNull()
        ->and($event->rationale)->toBe('Ready')
        ->and($event->recorded_by_user_id)->toBe($this->user->id)
        ->and($event->recorded_at)->not->toBeNull();
});

it('rejects submitting a change request that is not proposed', function () {
    $change = ($this->makeChange)('approved', 'approved');

    GovernanceServer::tool(SubmitChangeRequest::class, ['change_request_id' => $change->id])
        ->assertHasErrors(['Cannot submit a change request that is approved.']);

    expect($change->fresh()->status)->toBe('approved');
    expect(ChangeApprovalEvent::count())->toBe(0);
});

it('approves an under_review change request and records the decision', function () {
    $change = ($this->makeChange)('under_review');

    GovernanceServer::tool(ApproveChangeRequest::class, ['change_request_id' => $change->id, 'reason' => 'Looks good'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'under_review')
                ->where('to_status', 'approved')
                ->where('decision', 'approved')
                ->etc();
        });

    $change->refresh();
    expect($change->status)->toBe('approved')
        ->and($change->decision)->toBe('approved')
        ->and($change->decided_at)->not->toBeNull();

    $event = ChangeApprovalEvent::query()->sole();
    expect($event->to_decision)->toBe('approved')
        ->and($event->rationale)->toBe('Looks good');
});

it('rejects an under_review change request', function () {
    $change = ($this->makeChange)('under_review');

    GovernanceServer::tool(RejectChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk();

    $change->refresh();
    expect($change->status)->toBe('rejected')
        ->and($change->decision)->toBe('rejected');
});

it('defers an under_review change request', function () {
    $change = ($this->makeChange)('under_review');

    GovernanceServer::tool(DeferChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk();

    $change->refresh();
    expect($change->status)->toBe('deferred')
        ->and($change->decision)->toBe('deferred');
});

it('rejects approving a change request that is not under_review', function () {
    $change = ($this->makeChange)('proposed');

    GovernanceServer::tool(ApproveChangeRequest::class, ['change_request_id' => $change->id])
        ->assertHasErrors(['Cannot approve a change request that is proposed.']);

    expect($change->fresh()->decision)->toBeNull();
    expect(ChangeApprovalEvent::count())->toBe(0);
});

it('marks an approved change request as implemented', function () {
    $change = ($this->makeChange)('approved', 'approved');

    GovernanceServer::tool(MarkChangeRequestImplemented::class, ['change_request_id' => $change->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'approved')
                ->where('to_status', 'implemented')
                ->etc();
        });

    expect($change->fresh()->status)->toBe('implemented');
});

it('cancels a deferred change request', function () {
    $change = ($this->makeChange)('deferred', 'deferred');

    GovernanceServer::tool(CancelChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk();

    expect($change->fresh()->status)->toBe('cancelled');
});

it('rejects status passed to upsert-change-request with a pointer to the transition tools', function () {
    GovernanceServer::tool(UpsertChangeRequest::class, [
        'project_id' => $this->project->id,
        'title' => 'No status here',
        'category' => 'scope',
        'status' => 'approved',
    ])
        ->assertHasErrors(['Change request status is not set here. Use the submit-, approve-, reject-, defer-, mark-change-request-implemented, and cancel-change-request tools to move status through validated transitions.']);

    expect(ChangeRequest::where('title', 'No status here')->exists())->toBeFalse();
});

it('rejects decision fields passed to upsert-change-request', function () {
    GovernanceServer::tool(UpsertChangeRequest::class, [
        'project_id' => $this->project->id,
        'title' => 'No decision here',
        'category' => 'scope',
        'decision' => 'approved',
    ])
        ->assertHasErrors(['Change request decisions are recorded by the approve-, reject-, and defer-change-request tools, not set directly.']);
});

it('rejects a transition on a change request the user does not own', function () {
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
        'status' => 'proposed',
    ]);

    GovernanceServer::tool(SubmitChangeRequest::class, ['change_request_id' => $foreignChange->id])
        ->assertHasErrors();

    expect($foreignChange->fresh()->status)->toBe('proposed');
});
