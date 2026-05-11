<?php

use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\Requirement;
use App\Models\Source;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Support\Facades\File;

it('exports a project snapshot as markdown and json artifacts', function () {
    $project = Project::create([
        'name' => 'Apollo',
        'description' => 'Lunar mission planning.',
        'integrity_level' => 3,
    ]);
    ProjectPlan::create([
        'project_id' => $project->id,
        'status' => 'draft',
        'scope_summary' => 'Land safely.',
        'approach' => 'Incremental delivery.',
    ]);
    $source = Source::create([
        'project_id' => $project->id,
        'kind' => 'ticket',
        'title' => '#42 Guidance computer',
        'uri' => 'https://github.com/acme/apollo/issues/42',
        'external_ref' => 'github:acme/apollo#42',
        'body' => 'Issue body.',
    ]);
    $requirement = Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall compute guidance commands.',
        'source' => 'docs/reference/journeys/01-guidance.md',
        'acceptance_criteria' => ['Commands are visible to crew.'],
        'priority' => 'high',
    ]);
    $workItem = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => '#42 Guidance computer',
        'status' => 'done',
    ]);
    $workItem->requirements()->attach($requirement);
    WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'commit',
        'ref' => 'abc123',
        'url' => 'https://github.com/acme/apollo/commit/abc123',
    ]);

    $target = storage_path('framework/testing/project-export');
    File::deleteDirectory($target);

    $this->artisan('growth:export-project', [
        'project' => 'Apollo',
        'path' => $target,
    ])
        ->expectsOutputToContain('Exported Apollo')
        ->expectsOutputToContain('Requirements: 1')
        ->expectsOutputToContain('Sources: 1')
        ->expectsOutputToContain('Work items: 1')
        ->assertExitCode(0);

    expect(File::exists($target.'/manifest.json'))->toBeTrue();
    expect(File::get($target.'/project.md'))->toContain('# Apollo', 'Land safely.');
    expect(File::get($target.'/requirements.md'))->toContain('The system shall compute guidance commands.', 'Commands are visible to crew.');
    expect(File::get($target.'/work-items.md'))->toContain('#42 Guidance computer', 'commit: abc123');

    $manifest = json_decode(File::get($target.'/manifest.json'), true);
    expect($manifest['format'])->toBe('growth-workbench.project-export.v1');
    expect($manifest['counts'])->toBe([
        'requirements' => 1,
        'sources' => 1,
        'work_items' => 1,
    ]);

    $sources = json_decode(File::get($target.'/sources.json'), true);
    expect($sources[0]['id'])->toBe($source->id);
    expect($sources[0]['external_ref'])->toBe('github:acme/apollo#42');

    $traceability = json_decode(File::get($target.'/traceability.json'), true);
    expect($traceability['work_items'][0]['requirements'])->toBe([$requirement->id]);
    expect($traceability['work_items'][0]['delivery_links'][0]['ref'])->toBe('abc123');
});
