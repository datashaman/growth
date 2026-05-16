<?php

use App\Growth\Transitions\CancelRelease;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\PromoteRelease;
use App\Models\Project;
use App\Models\Release;
use App\Models\StatusTransition;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Transitions',
        'rigor_level' => 2,
    ]);

    $this->makeRelease = fn (string $status): Release => Release::create([
        'project_id' => $this->project->id,
        'version' => '1.0.0',
        'status' => $status,
    ]);
});

// ---- base transition action ----

it('applies a legal release transition and records an audit row', function () {
    $release = ($this->makeRelease)('planned');

    $transition = (new PromoteRelease)->apply($release, $this->user, 'Cutting RC');

    expect($release->fresh()->status)->toBe('candidate')
        ->and($transition->from_status)->toBe('planned')
        ->and($transition->to_status)->toBe('candidate')
        ->and($transition->reason)->toBe('Cutting RC')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and(StatusTransition::count())->toBe(1);
});

it('rejects an illegal source state without writing an audit row', function () {
    $release = ($this->makeRelease)('released');

    expect(fn () => (new CancelRelease)->apply($release))
        ->toThrow(IllegalTransitionException::class, 'Cannot cancel a release that is released.');

    expect($release->fresh()->status)->toBe('released')
        ->and(StatusTransition::count())->toBe(0);
});

// ---- webapp buttons ----

it('shows a promote button for a planned release and promotes it', function () {
    $release = ($this->makeRelease)('planned');

    $this->actingAs($this->user);

    Livewire::test('pages::evidence')
        ->assertSee('Promote')
        ->call('promoteRelease', $release->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($release->fresh()->status)->toBe('candidate')
        ->and(StatusTransition::query()->sole()->to_status)->toBe('candidate');
});

it('shows a mark-released button for a candidate release and releases it', function () {
    $release = ($this->makeRelease)('candidate');

    $this->actingAs($this->user);

    Livewire::test('pages::evidence')
        ->assertSee('Mark released')
        ->call('markReleaseReleased', $release->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($release->fresh()->status)->toBe('released');
});

it('rejects an illegal release transition from the webapp and warns the user', function () {
    $release = ($this->makeRelease)('released');

    $this->actingAs($this->user);

    Livewire::test('pages::evidence')
        ->call('cancelRelease', $release->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($release->fresh()->status)->toBe('released')
        ->and(StatusTransition::count())->toBe(0);
});
