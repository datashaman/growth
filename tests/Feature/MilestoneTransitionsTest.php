<?php

use App\Growth\Transitions\AchieveMilestone;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Milestones',
        'rigor_level' => 2,
    ]);

    $this->makeMilestone = fn (string $status): Milestone => Milestone::create([
        'project_id' => $this->project->id,
        'name' => 'Beta',
        'status' => $status,
    ]);

    $this->actingAs($this->user);
});

// ---- base action ----

it('rejects an illegal milestone transition without writing a row', function () {
    $milestone = ($this->makeMilestone)('achieved');

    expect(fn () => (new AchieveMilestone)->apply($milestone))
        ->toThrow(IllegalTransitionException::class, 'Cannot achieve a milestone that is achieved.');

    expect(StatusTransition::count())->toBe(0);
});

// ---- plan page buttons ----

it('achieves a milestone from the plan page', function () {
    $milestone = ($this->makeMilestone)('pending');
    $milestone->workItems()->attach(WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Ship it',
        'status' => 'done',
    ])->id);

    Livewire::test('pages::plan')
        ->call('achieveMilestone', $milestone->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($milestone->fresh()->status)->toBe('achieved');
    expect(StatusTransition::query()->sole()->to_status)->toBe('achieved');
});

it('warns the user when a plan page milestone transition is illegal', function () {
    $milestone = ($this->makeMilestone)('achieved');

    Livewire::test('pages::plan')
        ->call('achieveMilestone', $milestone->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($milestone->fresh()->status)->toBe('achieved')
        ->and(StatusTransition::count())->toBe(0);
});

it('shows transition controls only for pending milestones', function () {
    $milestone = ($this->makeMilestone)('pending');

    Livewire::test('pages::plan')
        ->assertSeeHtml("achieveMilestone('{$milestone->id}')");

    $milestone->update(['status' => 'achieved']);

    Livewire::test('pages::plan')
        ->assertDontSeeHtml("achieveMilestone('{$milestone->id}')");
});
