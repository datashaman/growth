<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Changes\ResolveChangeRequestByReference;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Growth',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/growth',
    ]);

    $this->changeRequest = ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Adjust telemetry scope',
        'category' => 'scope',
        'status' => 'approved',
    ]);
});

it('resolves a CR-NNN reference to its change request', function () {
    PlanningServer::tool(ResolveChangeRequestByReference::class, [
        'github_repo' => 'datashaman/growth',
        'reference' => 'CR-'.$this->changeRequest->number,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', true)
                ->where('github_repo', 'datashaman/growth')
                ->where('reference', 'CR-'.$this->changeRequest->number)
                ->where('change_request_id', $this->changeRequest->id)
                ->where('change_request_title', 'Adjust telemetry scope')
                ->where('change_request_status', 'approved');
        });
});

it('accepts a bare number, lowercase prefix, and leading zeros', function (string $reference) {
    PlanningServer::tool(ResolveChangeRequestByReference::class, [
        'github_repo' => 'datashaman/growth',
        'reference' => $reference,
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('found', true)
            ->where('change_request_id', $this->changeRequest->id)
            ->etc());
})->with([
    'bare number' => ['1'],
    'lowercase prefix' => ['cr-1'],
    'leading zeros' => ['CR-001'],
]);

it('reports not found when no change request carries the number', function () {
    PlanningServer::tool(ResolveChangeRequestByReference::class, [
        'github_repo' => 'datashaman/growth',
        'reference' => 'CR-999',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', false)
                ->where('github_repo', 'datashaman/growth')
                ->where('reference', 'CR-999')
                ->where('change_request_id', null)
                ->where('change_request_title', null)
                ->where('change_request_status', null);
        });
});

it('does not resolve a number in another workspace', function () {
    $other = User::factory()->create();
    $foreignProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/foreign',
    ]);
    $foreignChange = ChangeRequest::create([
        'project_id' => $foreignProject->id,
        'title' => 'Foreign change',
        'category' => 'scope',
    ]);

    PlanningServer::tool(ResolveChangeRequestByReference::class, [
        'github_repo' => 'datashaman/foreign',
        'reference' => 'CR-'.$foreignChange->number,
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('found', false)->etc());
});
