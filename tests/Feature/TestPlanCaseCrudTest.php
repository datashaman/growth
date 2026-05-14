<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

test('owner can create a test plan', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::test-plans.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'System smoke')
        ->set('level', 'system')
        ->set('scope', 'End-to-end happy path.')
        ->call('save')
        ->assertHasNoErrors();

    expect(TestPlan::query()->where('name', 'System smoke')->exists())->toBeTrue();
});

test('test plan level must be valid', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::test-plans.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'x')
        ->set('level', 'fake')
        ->call('save')
        ->assertHasErrors('level');
});

test('owner can edit a test plan', function () {
    $plan = $this->project->testPlans()->create([
        'name' => 'Old', 'level' => 'unit',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::test-plans.edit-modal')
        ->call('load', $plan->id)
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($plan->fresh()->name)->toBe('New');
});

test('test plan edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $bobPlan = $bobProject->testPlans()->create(['name' => 'Bob', 'level' => 'unit']);
    $this->actingAs($this->user);

    Livewire::test('pages::test-plans.edit-modal')
        ->call('load', $bobPlan->id)
        ->assertStatus(404);
});

test('owner can delete a test plan', function () {
    $plan = $this->project->testPlans()->create(['name' => 'Doomed', 'level' => 'unit']);
    $this->actingAs($this->user);

    Livewire::test('pages::test-plans.delete-modal')
        ->call('load', $plan->id)
        ->call('delete');

    expect(TestPlan::find($plan->id))->toBeNull();
});

test('delete modal surfaces case count warning', function () {
    $plan = $this->project->testPlans()->create(['name' => 'Busy', 'level' => 'unit']);
    $plan->cases()->create([
        'name' => 'c1',
        'expected_results' => 'ok',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::test-plans.delete-modal')
        ->call('load', $plan->id)
        ->assertSet('caseCount', 1);
});

test('owner can create a test case', function () {
    $plan = $this->project->testPlans()->create(['name' => 'P', 'level' => 'unit']);
    $this->actingAs($this->user);

    Livewire::test('pages::test-cases.create-modal')
        ->call('load', $plan->id)
        ->set('name', 'Smoke test')
        ->set('expected_results', 'passes')
        ->set('preconditions_text', "Logged in\nNetwork up")
        ->call('save')
        ->assertHasNoErrors();

    $case = TestCase::query()->where('name', 'Smoke test')->first();
    expect($case)->not->toBeNull()
        ->and($case->test_plan_id)->toBe($plan->id)
        ->and($case->preconditions)->toBe(['Logged in', 'Network up']);
});

test('test case requires expected_results', function () {
    $plan = $this->project->testPlans()->create(['name' => 'P', 'level' => 'unit']);
    $this->actingAs($this->user);

    Livewire::test('pages::test-cases.create-modal')
        ->call('load', $plan->id)
        ->set('name', 'x')
        ->set('expected_results', '')
        ->call('save')
        ->assertHasErrors(['expected_results' => 'required']);
});

test('test case create 404s for a plan in another project', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $bobPlan = $bobProject->testPlans()->create(['name' => 'Bob', 'level' => 'unit']);
    $this->actingAs($this->user);

    Livewire::test('pages::test-cases.create-modal')
        ->call('load', $bobPlan->id)
        ->assertStatus(404);
});

test('owner can edit a test case', function () {
    $plan = $this->project->testPlans()->create(['name' => 'P', 'level' => 'unit']);
    $case = $plan->cases()->create(['name' => 'Old', 'expected_results' => 'ok']);
    $this->actingAs($this->user);

    Livewire::test('pages::test-cases.edit-modal')
        ->call('load', $case->id)
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($case->fresh()->name)->toBe('New');
});

test('owner can delete a test case', function () {
    $plan = $this->project->testPlans()->create(['name' => 'P', 'level' => 'unit']);
    $case = $plan->cases()->create(['name' => 'Doomed', 'expected_results' => 'ok']);
    $this->actingAs($this->user);

    Livewire::test('pages::test-cases.delete-modal')
        ->call('load', $case->id)
        ->call('delete');

    expect(TestCase::find($case->id))->toBeNull();
});

test('verification page renders New test plan button', function () {
    $this->actingAs($this->user)
        ->get('/verification?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New test plan');
});
