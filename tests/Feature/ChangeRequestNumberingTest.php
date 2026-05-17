<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Tools\Changes\UpsertChangeRequest;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

function makeChangeRequest(Project $project, string $title): ChangeRequest
{
    return ChangeRequest::create([
        'project_id' => $project->id,
        'title' => $title,
        'category' => 'scope',
    ]);
}

test('change requests get a sequential per-project number', function () {
    $first = makeChangeRequest($this->project, 'First');
    $second = makeChangeRequest($this->project, 'Second');
    $third = makeChangeRequest($this->project, 'Third');

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2)
        ->and($third->number)->toBe(3);
});

test('numbering is independent per project', function () {
    $other = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mars Lander',
        'rigor_level' => 2,
    ]);

    makeChangeRequest($this->project, 'A');
    $otherFirst = makeChangeRequest($other, 'B');
    $projectSecond = makeChangeRequest($this->project, 'C');

    expect($otherFirst->number)->toBe(1)
        ->and($projectSecond->number)->toBe(2);
});

test('reference formats the number as CR-NNN', function () {
    $cr = makeChangeRequest($this->project, 'Padded');

    expect($cr->reference())->toBe('CR-001');
});

test('a project cannot have two change requests with the same number', function () {
    makeChangeRequest($this->project, 'First');

    $duplicate = new ChangeRequest([
        'project_id' => $this->project->id,
        'title' => 'Collides',
        'category' => 'scope',
    ]);
    $duplicate->number = 1;

    expect(fn () => $duplicate->save())->toThrow(QueryException::class);
});

test('the upsert-change-request tool returns the assigned number and reference', function () {
    GovernanceServer::tool(UpsertChangeRequest::class, [
        'project_id' => $this->project->id,
        'title' => 'Notification matrix deliverable',
        'category' => 'scope',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('number', 1)
                ->where('reference', 'CR-001')
                ->etc();
        });
});

test('a change request cannot be moved to another project', function () {
    $other = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mars Lander',
        'rigor_level' => 2,
    ]);
    $cr = makeChangeRequest($this->project, 'Stays put');

    expect(fn () => $cr->update(['project_id' => $other->id]))
        ->toThrow(RuntimeException::class);
});

test('the upsert-change-request tool rejects moving a request to another project', function () {
    $other = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mars Lander',
        'rigor_level' => 2,
    ]);
    $cr = makeChangeRequest($this->project, 'Stays put');

    GovernanceServer::tool(UpsertChangeRequest::class, [
        'id' => $cr->id,
        'project_id' => $other->id,
        'title' => 'Stays put',
        'category' => 'scope',
    ])->assertHasErrors();

    $cr->refresh();
    expect($cr->project_id)->toBe($this->project->id)
        ->and($cr->number)->toBe(1);
});
