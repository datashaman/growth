<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Feedback\SendFeedback;
use App\Models\Project;
use App\Models\ToolFeedback;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

it('creates feedback as new and reports the structured result', function () {
    ReadonlyServer::tool(SendFeedback::class, [
        'category' => 'difficulty',
        'summary' => 'upsert-requirements schema is confusing',
        'body' => 'I could not tell whether ids were required.',
        'tool_name' => 'upsert-requirements',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('category', 'difficulty')
                ->where('status', 'new')
                ->where('created', true)
                ->etc();
        });

    $feedback = ToolFeedback::sole();

    expect($feedback->status)->toBe('new')
        ->and($feedback->category)->toBe('difficulty')
        ->and($feedback->tool_name)->toBe('upsert-requirements')
        ->and($feedback->user_id)->toBe($this->user->id)
        ->and($feedback->agent_id)->toBeNull()
        ->and($feedback->workspace_id)->toBe($this->user->active_workspace_id);
});

it('rejects an unknown category', function () {
    ReadonlyServer::tool(SendFeedback::class, [
        'category' => 'rant',
        'summary' => 'Summary',
        'body' => 'Body',
    ])->assertHasErrors();

    expect(ToolFeedback::count())->toBe(0);
});

it('rejects a caller-supplied status', function () {
    ReadonlyServer::tool(SendFeedback::class, [
        'category' => 'bug',
        'summary' => 'Summary',
        'body' => 'Body',
        'status' => 'resolved',
    ])->assertHasErrors();

    expect(ToolFeedback::count())->toBe(0);
});

it('rejects an unknown project_id', function () {
    ReadonlyServer::tool(SendFeedback::class, [
        'category' => 'suggestion',
        'summary' => 'Summary',
        'body' => 'Body',
        'project_id' => 'not-a-real-ulid',
    ])->assertHasErrors();

    expect(ToolFeedback::count())->toBe(0);
});

it('links an owned project when one is given', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Feedback project',
        'rigor_level' => 2,
    ]);

    ReadonlyServer::tool(SendFeedback::class, [
        'category' => 'missing_capability',
        'summary' => 'No tool to bulk-archive requirements',
        'body' => 'I wanted to archive ten requirements at once.',
        'project_id' => $project->id,
    ])->assertOk();

    expect(ToolFeedback::sole()->project_id)->toBe($project->id);
});

it('normalizes underscore tool names to kebab names', function () {
    ReadonlyServer::tool(SendFeedback::class, [
        'category' => 'suggestion',
        'summary' => 'Tool names should be consistent',
        'body' => 'The feedback queue should use the canonical MCP tool name.',
        'tool_name' => 'get_mockup',
    ])->assertOk();

    expect(ToolFeedback::sole()->tool_name)->toBe('get-mockup');
});
