<?php

use App\Growth\Search\SearchService;
use App\Models\Project;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->workspaceId = $this->user->active_workspace_id;

    $this->project = Project::create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Apollo Platform',
        'rigor_level' => 2,
    ]);

    $this->search = fn (string $q, ?array $types = null, int $limit = SearchService::DEFAULT_LIMIT) => app(SearchService::class)->search($q, $types, $limit);
});

it('matches a substring across entity types', function () {
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Apollo launch checklist',
        'status' => 'todo',
    ]);
    Risk::create([
        'project_id' => $this->project->id,
        'title' => 'Apollo dependency drift',
        'category' => 'technical',
        'probability' => 'medium',
        'impact' => 'high',
        'status' => 'identified',
    ]);

    $hits = ($this->search)('apollo');

    expect($hits->pluck('type')->unique()->all())
        ->toContain('project', 'work_item', 'risk');
});

it('ranks an exact prefix above a word-boundary match', function () {
    Project::create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Platform metrics',
        'rigor_level' => 2,
    ]);

    // 'Apollo Platform' matches 'platform' only at a word boundary (tier 2);
    // 'Platform metrics' matches as an exact prefix (tier 3) and must rank first.
    $hits = ($this->search)('platform', ['project']);

    expect($hits->first()->label)->toBe('Platform metrics');
});

it('caps results per entity type', function () {
    foreach (range(1, 8) as $i) {
        Project::create([
            'workspace_id' => $this->workspaceId,
            'name' => "Beacon project {$i}",
            'rigor_level' => 2,
        ]);
    }

    $hits = ($this->search)('beacon', ['project'], 50);

    expect($hits)->toHaveCount(5);
});

it('honours the global result limit', function () {
    foreach (range(1, 8) as $i) {
        WorkItem::create([
            'project_id' => $this->project->id,
            'kind' => 'task',
            'name' => "Comet task {$i}",
            'status' => 'todo',
        ]);
    }

    $hits = ($this->search)('comet', null, 3);

    expect($hits)->toHaveCount(3);
});

it('never returns artifacts from another workspace', function () {
    $other = User::factory()->create();
    Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Apollo secret',
        'rigor_level' => 2,
    ]);

    $labels = ($this->search)('apollo', ['project'])->pluck('label')->all();

    expect($labels)
        ->toContain('Apollo Platform')
        ->not->toContain('Apollo secret');
});

it('returns nothing for a query shorter than two characters', function () {
    expect(($this->search)('a'))->toBeEmpty();
});

it('resolves a webapp route and matched field for a hit', function () {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Orion handoff',
        'status' => 'todo',
    ]);

    $hit = ($this->search)('orion handoff', ['work_item'])->first();

    expect($hit->route)->toBe('/work-items/'.$workItem->id)
        ->and($hit->matchedField)->toBe('name')
        ->and($hit->projectId)->toBe($this->project->id);
});
