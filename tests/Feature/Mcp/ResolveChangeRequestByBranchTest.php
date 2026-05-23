<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Changes\ResolveChangeRequestByBranch;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestDeliveryLink;
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

it('resolves a branch delivery link to its change request', function () {
    ChangeRequestDeliveryLink::create([
        'change_request_id' => $this->changeRequest->id,
        'type' => 'branch',
        'ref' => 'feature/telemetry-cr',
    ]);

    PlanningServer::tool(ResolveChangeRequestByBranch::class, [
        'github_repo' => 'datashaman/growth',
        'branch' => 'feature/telemetry-cr',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', true)
                ->where('ambiguous', false)
                ->where('github_repo', 'datashaman/growth')
                ->where('branch', 'feature/telemetry-cr')
                ->where('change_request_id', $this->changeRequest->id)
                ->where('change_request_title', 'Adjust telemetry scope')
                ->where('change_request_status', 'approved');
        });
});

it('reports not found when no branch link matches', function () {
    PlanningServer::tool(ResolveChangeRequestByBranch::class, [
        'github_repo' => 'datashaman/growth',
        'branch' => 'feature/never-bound',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', false)
                ->where('ambiguous', false)
                ->where('change_request_id', null)
                ->where('change_request_title', null)
                ->where('change_request_status', null)
                ->etc();
        });
});

it('ignores a pull_request delivery link that shares the ref', function () {
    ChangeRequestDeliveryLink::create([
        'change_request_id' => $this->changeRequest->id,
        'type' => 'pull_request',
        'ref' => 'shared-ref',
    ]);

    PlanningServer::tool(ResolveChangeRequestByBranch::class, [
        'github_repo' => 'datashaman/growth',
        'branch' => 'shared-ref',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('found', false)->etc());
});

it('reports ambiguity when two change requests share a branch link', function () {
    $second = ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Also telemetry',
        'category' => 'scope',
    ]);

    foreach ([$this->changeRequest, $second] as $changeRequest) {
        ChangeRequestDeliveryLink::create([
            'change_request_id' => $changeRequest->id,
            'type' => 'branch',
            'ref' => 'feature/contested',
        ]);
    }

    PlanningServer::tool(ResolveChangeRequestByBranch::class, [
        'github_repo' => 'datashaman/growth',
        'branch' => 'feature/contested',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', false)
                ->where('ambiguous', true)
                ->where('change_request_id', null)
                ->etc();
        });
});

it('does not resolve a branch bound in another workspace', function () {
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
    ChangeRequestDeliveryLink::create([
        'change_request_id' => $foreignChange->id,
        'type' => 'branch',
        'ref' => 'feature/telemetry-cr',
    ]);

    PlanningServer::tool(ResolveChangeRequestByBranch::class, [
        'github_repo' => 'datashaman/foreign',
        'branch' => 'feature/telemetry-cr',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('found', false)->etc());
});
