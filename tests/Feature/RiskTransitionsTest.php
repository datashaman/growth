<?php

use App\Growth\Transitions\AcceptRisk;
use App\Growth\Transitions\AssessRisk;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\MarkRiskRealized;
use App\Models\Project;
use App\Models\Risk;
use App\Models\StatusTransition;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
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

// ---- transition actions ----

it('applies a legal risk transition and records an audit row', function () {
    $risk = ($this->makeRisk)('identified');

    $transition = (new AssessRisk)->apply($risk, $this->user, 'Quantified');

    expect($risk->fresh()->status)->toBe('assessed')
        ->and($transition->from_status)->toBe('identified')
        ->and($transition->to_status)->toBe('assessed')
        ->and($transition->reason)->toBe('Quantified')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and(StatusTransition::count())->toBe(1);
});

it('rejects an illegal risk source state without writing an audit row', function () {
    $risk = ($this->makeRisk)('closed');

    expect(fn () => (new MarkRiskRealized)->apply($risk))
        ->toThrow(IllegalTransitionException::class, 'Cannot mark as realized a risk that is closed.');

    expect($risk->fresh()->status)->toBe('closed')
        ->and(StatusTransition::count())->toBe(0);
});

it('accepts a risk from either identified or assessed', function () {
    $identified = ($this->makeRisk)('identified');
    $assessed = ($this->makeRisk)('assessed');

    (new AcceptRisk)->apply($identified);
    (new AcceptRisk)->apply($assessed);

    expect($identified->fresh()->status)->toBe('accepted')
        ->and($assessed->fresh()->status)->toBe('accepted');
});

// ---- webapp buttons ----

it('shows an assess button for an identified risk and assesses it', function () {
    $risk = ($this->makeRisk)('identified');

    $this->actingAs($this->user);

    Livewire::test('pages::risks.show', ['risk' => $risk])
        ->assertSee('Assess')
        ->call('assessRisk')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($risk->fresh()->status)->toBe('assessed');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('assessed')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id);
});

it('walks a risk through mitigation from the webapp', function () {
    $risk = ($this->makeRisk)('assessed');

    $this->actingAs($this->user);

    Livewire::test('pages::risks.show', ['risk' => $risk])
        ->assertSee('Start mitigation')
        ->call('startRiskMitigation')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);
    expect($risk->fresh()->status)->toBe('mitigating');

    Livewire::test('pages::risks.show', ['risk' => $risk->fresh()])
        ->assertSee('Mark mitigated')
        ->call('markRiskMitigated')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);
    expect($risk->fresh()->status)->toBe('mitigated');

    Livewire::test('pages::risks.show', ['risk' => $risk->fresh()])
        ->assertSee('Close')
        ->call('closeRisk')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);
    expect($risk->fresh()->status)->toBe('closed');
});

it('marks a risk realized from the webapp', function () {
    $risk = ($this->makeRisk)('mitigating');

    $this->actingAs($this->user);

    Livewire::test('pages::risks.show', ['risk' => $risk])
        ->assertSee('Mark realized')
        ->call('markRiskRealized')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($risk->fresh()->status)->toBe('realized');
});

it('rejects an illegal risk transition from the webapp and warns the user', function () {
    $risk = ($this->makeRisk)('closed');

    $this->actingAs($this->user);

    Livewire::test('pages::risks.show', ['risk' => $risk])
        ->call('assessRisk')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($risk->fresh()->status)->toBe('closed')
        ->and(StatusTransition::count())->toBe(0);
});

it('404s the risk page for a user from another workspace', function () {
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

    $this->actingAs($this->user)
        ->get(route('risks.show', $foreignRisk))
        ->assertNotFound();
});
