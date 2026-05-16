<?php

use App\Mcp\Servers\VerificationServer;
use App\Mcp\Tools\Verification\CloseAnomaly;
use App\Mcp\Tools\Verification\ReopenAnomaly;
use App\Mcp\Tools\Verification\ResolveAnomaly;
use App\Mcp\Tools\Verification\StartAnomalyInvestigation;
use App\Mcp\Tools\Verification\UpsertAnomaly;
use App\Models\Anomaly;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Anomaly transitions',
        'rigor_level' => 2,
    ]);

    $this->makeAnomaly = fn (string $status): Anomaly => Anomaly::create([
        'project_id' => $this->project->id,
        'severity' => 'high',
        'status' => $status,
        'summary' => 'Checkout fails',
        'description' => 'The cart total is wrong.',
    ]);
});

it('starts investigation on an open anomaly and records a transition', function () {
    $anomaly = ($this->makeAnomaly)('open');

    VerificationServer::tool(StartAnomalyInvestigation::class, ['anomaly_id' => $anomaly->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($anomaly) {
            $json->where('anomaly_id', $anomaly->id)
                ->where('from_status', 'open')
                ->where('to_status', 'investigating')
                ->etc();
        });

    expect($anomaly->fresh()->status)->toBe('investigating');

    $transition = StatusTransition::query()->sole();
    expect($transition->from_status)->toBe('open')
        ->and($transition->to_status)->toBe('investigating')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($transition->transitionable->is($anomaly))->toBeTrue();
});

it('rejects starting investigation on an anomaly that is not open', function () {
    $anomaly = ($this->makeAnomaly)('resolved');

    VerificationServer::tool(StartAnomalyInvestigation::class, ['anomaly_id' => $anomaly->id])
        ->assertHasErrors(['Cannot investigate an anomaly that is resolved.']);

    expect($anomaly->fresh()->status)->toBe('resolved');
    expect(StatusTransition::count())->toBe(0);
});

it('resolves an investigating anomaly', function () {
    $anomaly = ($this->makeAnomaly)('investigating');

    VerificationServer::tool(ResolveAnomaly::class, ['anomaly_id' => $anomaly->id, 'reason' => 'Patched'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'investigating')
                ->where('to_status', 'resolved')
                ->etc();
        });

    expect($anomaly->fresh()->status)->toBe('resolved');
    expect(StatusTransition::query()->sole()->reason)->toBe('Patched');
});

it('rejects resolving an anomaly that is not investigating', function () {
    $anomaly = ($this->makeAnomaly)('open');

    VerificationServer::tool(ResolveAnomaly::class, ['anomaly_id' => $anomaly->id])
        ->assertHasErrors(['Cannot resolve an anomaly that is open.']);

    expect($anomaly->fresh()->status)->toBe('open');
});

it('closes a resolved anomaly', function () {
    $anomaly = ($this->makeAnomaly)('resolved');

    VerificationServer::tool(CloseAnomaly::class, ['anomaly_id' => $anomaly->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'resolved')
                ->where('to_status', 'closed')
                ->etc();
        });

    expect($anomaly->fresh()->status)->toBe('closed');
});

it('rejects closing an anomaly that is not resolved', function () {
    $anomaly = ($this->makeAnomaly)('investigating');

    VerificationServer::tool(CloseAnomaly::class, ['anomaly_id' => $anomaly->id])
        ->assertHasErrors(['Cannot close an anomaly that is investigating.']);

    expect($anomaly->fresh()->status)->toBe('investigating');
});

it('reopens a resolved anomaly', function () {
    $anomaly = ($this->makeAnomaly)('resolved');

    VerificationServer::tool(ReopenAnomaly::class, ['anomaly_id' => $anomaly->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'resolved')
                ->where('to_status', 'open')
                ->etc();
        });

    expect($anomaly->fresh()->status)->toBe('open');
});

it('reopens a closed anomaly', function () {
    $anomaly = ($this->makeAnomaly)('closed');

    VerificationServer::tool(ReopenAnomaly::class, ['anomaly_id' => $anomaly->id])->assertOk();

    expect($anomaly->fresh()->status)->toBe('open');
});

it('rejects reopening an anomaly that is open', function () {
    $anomaly = ($this->makeAnomaly)('open');

    VerificationServer::tool(ReopenAnomaly::class, ['anomaly_id' => $anomaly->id])
        ->assertHasErrors(['Cannot reopen an anomaly that is open.']);

    expect($anomaly->fresh()->status)->toBe('open');
});

it('rejects status passed to upsert-anomaly with a pointer to the transition tools', function () {
    VerificationServer::tool(UpsertAnomaly::class, [
        'project_id' => $this->project->id,
        'severity' => 'low',
        'summary' => 'No status here',
        'description' => 'Body text.',
        'status' => 'resolved',
    ])->assertHasErrors([
        'Anomaly status is not set here. Use the start-anomaly-investigation, resolve-anomaly, close-anomaly, and reopen-anomaly tools to move status through validated transitions.',
    ]);

    expect(Anomaly::where('summary', 'No status here')->exists())->toBeFalse();
});

it('creates an anomaly as open through upsert-anomaly', function () {
    VerificationServer::tool(UpsertAnomaly::class, [
        'project_id' => $this->project->id,
        'severity' => 'medium',
        'summary' => 'Fresh anomaly',
        'description' => 'Body text.',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('status', 'open')->etc();
        });

    expect(Anomaly::where('summary', 'Fresh anomaly')->sole()->status)->toBe('open');
});

it('rejects a transition on an anomaly the user does not own', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignAnomaly = Anomaly::create([
        'project_id' => $strangerProject->id,
        'severity' => 'low',
        'status' => 'open',
        'summary' => 'Off limits',
        'description' => 'Body text.',
    ]);

    VerificationServer::tool(StartAnomalyInvestigation::class, ['anomaly_id' => $foreignAnomaly->id])
        ->assertHasErrors();

    expect($foreignAnomaly->fresh()->status)->toBe('open');
});
