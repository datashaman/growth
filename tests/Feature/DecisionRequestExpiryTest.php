<?php

use App\Models\DecisionRequest;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $owner = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $owner->active_workspace_id,
        'name' => 'Festival Market',
        'rigor_level' => 2,
    ]);
    $this->role = Role::create(['project_id' => $this->project->id, 'name' => 'Product Owner']);

    $this->makeRequest = fn (array $attributes = []): DecisionRequest => DecisionRequest::factory()->create(array_merge([
        'project_id' => $this->project->id,
        'target_role_id' => $this->role->id,
    ], $attributes));
});

it('expires open decision requests past their deadline', function () {
    $overdue = ($this->makeRequest)(['deadline' => now()->subDay()]);
    $future = ($this->makeRequest)(['deadline' => now()->addDay()]);
    $noDeadline = ($this->makeRequest)(['deadline' => null]);

    $this->artisan('decision-requests:expire')->assertSuccessful();

    expect($overdue->fresh()->status)->toBe('expired')
        ->and($future->fresh()->status)->toBe('open')
        ->and($noDeadline->fresh()->status)->toBe('open');
});

it('records an auditable transition for an expired request', function () {
    $overdue = ($this->makeRequest)(['deadline' => now()->subHour()]);

    $this->artisan('decision-requests:expire')->assertSuccessful();

    $transition = $overdue->statusTransitions()->sole();

    expect($transition->from_status)->toBe('open')
        ->and($transition->to_status)->toBe('expired');
});

it('leaves an already-answered request untouched', function () {
    $answered = ($this->makeRequest)(['status' => 'answered', 'deadline' => now()->subDay()]);

    $this->artisan('decision-requests:expire')->assertSuccessful();

    expect($answered->fresh()->status)->toBe('answered')
        ->and($answered->statusTransitions()->count())->toBe(0);
});
