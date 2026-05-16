<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\AcceptRisk;
use App\Mcp\Tools\Plan\AssessRisk;
use App\Mcp\Tools\Plan\CloseRisk;
use App\Mcp\Tools\Plan\MarkRiskMitigated;
use App\Mcp\Tools\Plan\MarkRiskRealized;
use App\Mcp\Tools\Plan\StartRiskMitigation;
use App\Mcp\Tools\Plan\UpsertRisk;
use App\Models\Project;
use App\Models\Risk;
use App\Models\StatusTransition;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Risk transitions',
        'rigor_level' => 2,
    ]);

    $this->makeRisk = fn (string $status): Risk => Risk::create([
        'project_id' => $this->project->id,
        'title' => 'Vendor lock-in',
        'category' => 'technical',
        'probability' => 'medium',
        'impact' => 'high',
        'status' => $status,
    ]);
});

it('assesses an identified risk and records a transition', function () {
    $risk = ($this->makeRisk)('identified');

    PlanningServer::tool(AssessRisk::class, ['risk_id' => $risk->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($risk) {
            $json->where('risk_id', $risk->id)
                ->where('from_status', 'identified')
                ->where('to_status', 'assessed')
                ->etc();
        });

    expect($risk->fresh()->status)->toBe('assessed');

    $transition = StatusTransition::query()->sole();
    expect($transition->from_status)->toBe('identified')
        ->and($transition->to_status)->toBe('assessed')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($transition->transitionable->is($risk))->toBeTrue();
});

it('rejects assessing a risk that is not identified', function () {
    $risk = ($this->makeRisk)('mitigating');

    PlanningServer::tool(AssessRisk::class, ['risk_id' => $risk->id])
        ->assertHasErrors(['Cannot assess a risk that is mitigating.']);

    expect($risk->fresh()->status)->toBe('mitigating');
    expect(StatusTransition::count())->toBe(0);
});

it('starts mitigation on an assessed risk', function () {
    $risk = ($this->makeRisk)('assessed');

    PlanningServer::tool(StartRiskMitigation::class, ['risk_id' => $risk->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'assessed')
                ->where('to_status', 'mitigating')
                ->etc();
        });

    expect($risk->fresh()->status)->toBe('mitigating');
});

it('rejects starting mitigation on a risk that is not assessed', function () {
    $risk = ($this->makeRisk)('identified');

    PlanningServer::tool(StartRiskMitigation::class, ['risk_id' => $risk->id])
        ->assertHasErrors(['Cannot start mitigation on a risk that is identified.']);

    expect($risk->fresh()->status)->toBe('identified');
});

it('marks a mitigating risk as mitigated', function () {
    $risk = ($this->makeRisk)('mitigating');

    PlanningServer::tool(MarkRiskMitigated::class, ['risk_id' => $risk->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'mitigating')
                ->where('to_status', 'mitigated')
                ->etc();
        });

    expect($risk->fresh()->status)->toBe('mitigated');
});

it('rejects marking a risk mitigated when it is not mitigating', function () {
    $risk = ($this->makeRisk)('assessed');

    PlanningServer::tool(MarkRiskMitigated::class, ['risk_id' => $risk->id])
        ->assertHasErrors(['Cannot mark as mitigated a risk that is assessed.']);

    expect($risk->fresh()->status)->toBe('assessed');
});

it('accepts an identified risk', function () {
    $risk = ($this->makeRisk)('identified');

    PlanningServer::tool(AcceptRisk::class, ['risk_id' => $risk->id])
        ->assertOk();

    expect($risk->fresh()->status)->toBe('accepted');
});

it('accepts an assessed risk', function () {
    $risk = ($this->makeRisk)('assessed');

    PlanningServer::tool(AcceptRisk::class, ['risk_id' => $risk->id])
        ->assertOk();

    expect($risk->fresh()->status)->toBe('accepted');
});

it('rejects accepting a risk that is mitigating', function () {
    $risk = ($this->makeRisk)('mitigating');

    PlanningServer::tool(AcceptRisk::class, ['risk_id' => $risk->id])
        ->assertHasErrors(['Cannot accept a risk that is mitigating.']);

    expect($risk->fresh()->status)->toBe('mitigating');
});

it('marks an active risk as realized', function () {
    $risk = ($this->makeRisk)('mitigating');

    PlanningServer::tool(MarkRiskRealized::class, ['risk_id' => $risk->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'mitigating')
                ->where('to_status', 'realized')
                ->etc();
        });

    expect($risk->fresh()->status)->toBe('realized');
});

it('rejects marking a closed risk as realized', function () {
    $risk = ($this->makeRisk)('closed');

    PlanningServer::tool(MarkRiskRealized::class, ['risk_id' => $risk->id])
        ->assertHasErrors(['Cannot mark as realized a risk that is closed.']);

    expect($risk->fresh()->status)->toBe('closed');
    expect(StatusTransition::count())->toBe(0);
});

it('rejects marking an already-realized risk as realized', function () {
    $risk = ($this->makeRisk)('realized');

    PlanningServer::tool(MarkRiskRealized::class, ['risk_id' => $risk->id])
        ->assertHasErrors(['Cannot mark as realized a risk that is realized.']);

    expect($risk->fresh()->status)->toBe('realized');
    expect(StatusTransition::count())->toBe(0);
});

it('closes a mitigated risk', function () {
    $risk = ($this->makeRisk)('mitigated');

    PlanningServer::tool(CloseRisk::class, ['risk_id' => $risk->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'mitigated')
                ->where('to_status', 'closed')
                ->etc();
        });

    expect($risk->fresh()->status)->toBe('closed');
});

it('closes an accepted risk', function () {
    $risk = ($this->makeRisk)('accepted');

    PlanningServer::tool(CloseRisk::class, ['risk_id' => $risk->id])->assertOk();

    expect($risk->fresh()->status)->toBe('closed');
});

it('closes a realized risk', function () {
    $risk = ($this->makeRisk)('realized');

    PlanningServer::tool(CloseRisk::class, ['risk_id' => $risk->id])->assertOk();

    expect($risk->fresh()->status)->toBe('closed');
});

it('rejects closing a risk that is identified', function () {
    $risk = ($this->makeRisk)('identified');

    PlanningServer::tool(CloseRisk::class, ['risk_id' => $risk->id])
        ->assertHasErrors(['Cannot close a risk that is identified.']);

    expect($risk->fresh()->status)->toBe('identified');
});

it('records the reason on a risk transition', function () {
    $risk = ($this->makeRisk)('identified');

    PlanningServer::tool(AssessRisk::class, ['risk_id' => $risk->id, 'reason' => 'Quantified exposure'])
        ->assertOk();

    expect(StatusTransition::query()->sole()->reason)->toBe('Quantified exposure');
});

it('rejects status passed to upsert-risk with a pointer to the transition tools', function () {
    PlanningServer::tool(UpsertRisk::class, [
        'project_id' => $this->project->id,
        'title' => 'No status here',
        'category' => 'technical',
        'probability' => 'low',
        'impact' => 'low',
        'status' => 'mitigating',
    ])->assertHasErrors([
        'Risk status is not set here. Use the assess-risk, start-risk-mitigation, mark-risk-mitigated, accept-risk, mark-risk-realized, and close-risk tools to move status through validated transitions.',
    ]);

    expect(Risk::where('title', 'No status here')->exists())->toBeFalse();
});

it('creates a risk as identified through upsert-risk', function () {
    PlanningServer::tool(UpsertRisk::class, [
        'project_id' => $this->project->id,
        'title' => 'Fresh risk',
        'category' => 'schedule',
        'probability' => 'low',
        'impact' => 'medium',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('status', 'identified')->etc();
        });

    expect(Risk::where('title', 'Fresh risk')->sole()->status)->toBe('identified');
});

it('rejects a transition on a risk the user does not own', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignRisk = Risk::create([
        'project_id' => $strangerProject->id,
        'title' => 'Off limits',
        'category' => 'technical',
        'probability' => 'low',
        'impact' => 'low',
        'status' => 'identified',
    ]);

    PlanningServer::tool(AssessRisk::class, ['risk_id' => $foreignRisk->id])
        ->assertHasErrors();

    expect($foreignRisk->fresh()->status)->toBe('identified');
});
