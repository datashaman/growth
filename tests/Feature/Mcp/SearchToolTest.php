<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Search\Search;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Apollo Platform',
        'rigor_level' => 2,
    ]);
});

it('returns workspace-scoped hits with the documented shape', function () {
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Apollo launch checklist',
        'status' => 'todo',
    ]);

    ReadonlyServer::tool(Search::class, ['query' => 'apollo'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('query', 'apollo')
                ->where('total', 2)
                ->has('results', 2)
                ->has('results.0', fn ($hit) => $hit
                    ->hasAll(['type', 'id', 'label', 'project_id', 'matched_field', 'route']))
                ->etc();
        });
});

it('restricts results to the requested types', function () {
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Apollo launch checklist',
        'status' => 'todo',
    ]);

    ReadonlyServer::tool(Search::class, ['query' => 'apollo', 'types' => ['project']])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('results.0.type', 'project')
                ->etc();
        });
});

it('honours the limit argument', function () {
    foreach (range(1, 6) as $i) {
        WorkItem::create([
            'project_id' => $this->project->id,
            'kind' => 'task',
            'name' => "Comet task {$i}",
            'status' => 'todo',
        ]);
    }

    ReadonlyServer::tool(Search::class, ['query' => 'comet', 'limit' => 2])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('total', 2)->has('results', 2)->etc());
});

it('rejects a query shorter than two characters', function () {
    ReadonlyServer::tool(Search::class, ['query' => 'a'])->assertHasErrors();
});

it('only returns artifacts from the active workspace', function () {
    $other = User::factory()->create();
    Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Apollo secret',
        'rigor_level' => 2,
    ]);

    ReadonlyServer::tool(Search::class, ['query' => 'apollo', 'types' => ['project']])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('results.0.label', 'Apollo Platform')
                ->etc();
        });
});

it('is registered on the read-capable servers and marked read-only', function () {
    $tools = (new ReflectionClass(ReadonlyServer::class))->getDefaultProperties()['tools'];
    expect($tools)->toContain(Search::class);

    $attributes = (new ReflectionClass(Search::class))->getAttributes(IsReadOnly::class);
    expect($attributes)->not->toBeEmpty();
});
