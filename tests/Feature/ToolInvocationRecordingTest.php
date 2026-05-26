<?php

use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\ManagementServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Feedback\ListToolInvocations;
use App\Mcp\Tools\Manifest\ExportManifest;
use App\Mcp\Tools\Projects\UpsertProject;
use App\Models\Project;
use App\Models\ToolInvocation;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

it('records a row for a successful tool call', function () {
    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    $row = ToolInvocation::sole();

    expect($row->tool_name)->toBe('who-am-i')
        ->and($row->success)->toBeTrue()
        ->and($row->user_id)->toBe($this->user->id)
        ->and($row->workspace_id)->toBe($this->user->active_workspace_id)
        ->and($row->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($row->args_shape)->toBeArray()
        ->and($row->args_full)->toBeNull()
        ->and($row->return_full)->toBeNull();
});

it('records a row when a tool returns an error', function () {
    AllServer::tool(UpsertProject::class, ['rigor_level' => 1])->assertHasErrors();

    $row = ToolInvocation::sole();

    expect($row->tool_name)->toBe('upsert-project')
        ->and($row->success)->toBeFalse()
        ->and($row->error_class)->toBe('tool_error')
        ->and($row->error_message)->not->toBeNull();
});

it('records a streamed (generator) tool with its captured result', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Recorded Export',
        'rigor_level' => 2,
        'status' => 'active',
    ]);

    ManagementServer::tool(ExportManifest::class, ['project_id' => $project->id])->assertOk();

    $row = ToolInvocation::sole();

    // A generator tool runs its body lazily as the transport drains the
    // stream; the recorder must wait for that drain before capturing — so a
    // populated `return_shape` proves the real result was seen, not null.
    expect($row->tool_name)->toBe('export-manifest')
        ->and($row->success)->toBeTrue()
        ->and($row->return_shape)->toBeArray()
        ->and($row->return_shape)->not->toBeEmpty();
});

it('records a streamed tool that errors as a failed invocation', function () {
    ManagementServer::tool(ExportManifest::class, [])->assertHasErrors();

    $row = ToolInvocation::sole();

    expect($row->tool_name)->toBe('export-manifest')
        ->and($row->success)->toBeFalse()
        ->and($row->error_class)->toBe('tool_error');
});

it('captures full payloads when the workspace opts in', function () {
    Workspace::query()->whereKey($this->user->active_workspace_id)->update(['mcp_capture_payloads' => true]);

    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    $row = ToolInvocation::sole();

    expect($row->args_full)->toBeArray()
        ->and($row->return_full)->toBeArray();
});

it('exposes recorded rows via list-tool-invocations', function () {
    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    ReadonlyServer::tool(ListToolInvocations::class)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 1)->etc();
        });

    expect(ToolInvocation::count())->toBe(2);
});

it('normalizes underscore tool names on invocation records and filters', function () {
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'tool_name' => 'delete_work_items',
        'transport' => 'http',
        'success' => true,
        'duration_ms' => 1,
        'args_shape' => [],
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    expect(ToolInvocation::sole()->tool_name)->toBe('delete-work-items');

    ReadonlyServer::tool(ListToolInvocations::class, ['tool_name' => 'delete_work_items'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('results.0.tool_name', 'delete-work-items')
                ->etc();
        });
});

it('prune command removes old rows', function () {
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'tool_name' => 'who-am-i',
        'transport' => 'http',
        'success' => true,
        'duration_ms' => 1,
        'args_shape' => [],
        'started_at' => now()->subDays(120),
        'completed_at' => now()->subDays(120),
    ]);
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'tool_name' => 'who-am-i',
        'transport' => 'http',
        'success' => true,
        'duration_ms' => 1,
        'args_shape' => [],
        'started_at' => now()->subDays(10),
        'completed_at' => now()->subDays(10),
    ]);

    $this->artisan('model:prune', ['--model' => [ToolInvocation::class]])->assertSuccessful();

    expect(ToolInvocation::count())->toBe(1);
});
