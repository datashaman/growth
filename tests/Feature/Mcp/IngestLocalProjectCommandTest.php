<?php

use App\Models\Project;
use App\Models\Requirement;
use App\Models\Source;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

it('imports local docs github issues and commit evidence', function () {
    $repo = storage_path('framework/testing/local-project');
    $bin = storage_path('framework/testing/bin');

    File::deleteDirectory($repo);
    File::deleteDirectory($bin);
    File::makeDirectory($repo.'/docs/reference/journeys', 0777, true);
    File::makeDirectory($bin, 0777, true);

    file_put_contents($repo.'/composer.json', json_encode(['name' => 'acme/festival-ops']));
    file_put_contents($repo.'/README.md', "# Festival Ops\n\nRuns event operations.\n");
    file_put_contents($repo.'/DESIGN.md', "# Design\n\n## Design Principles\n\n- Dense but scannable\n\n## Anti-references\n\n- Generic SaaS\n");
    file_put_contents($repo.'/TODOS.md', "# TODOs\n\n## Implementation Order\n\n1. [x] Ship ticketing\n2. [ ] Reconcile reports\n");
    file_put_contents($repo.'/docs/reference/journeys/01-ticketing.md', "# Journey: Ticketing\n\n## User Story\n\nAs an operator, I sell tickets.\n\n## Acceptance Criteria\n\n- [ ] I can create ticket types\n- [ ] I can inspect sales totals\n");

    file_put_contents($bin.'/gh', <<<'SH'
#!/bin/sh
if [ "$1" = "pr" ]; then
cat <<'JSON'
[{"number":43,"title":"Ship ticketing PR","body":"Tracks #42.","url":"https://github.com/acme/festival-ops/pull/43","mergeCommit":{"oid":"MERGE_SHA"},"closingIssuesReferences":[]}]
JSON
exit 0
fi
cat <<'JSON'
[{"number":42,"title":"Build ticketing","state":"OPEN","body":"Ticketing body","url":"https://github.com/acme/festival-ops/issues/42","labels":[{"name":"feature"}],"milestone":{"title":"M1"}}]
JSON
SH);
    chmod($bin.'/gh', 0755);

    (new Process(['git', 'init'], $repo))->mustRun();
    (new Process(['git', 'remote', 'add', 'origin', 'git@github.com:acme/festival-ops.git'], $repo))->mustRun();
    (new Process(['git', 'add', '.'], $repo))->mustRun();
    (new Process([
        'git',
        '-c',
        'user.email=test@example.com',
        '-c',
        'user.name=Test User',
        'commit',
        '-m',
        'feat: initial ticketing (#42)',
    ], $repo))->mustRun();

    $originalPath = getenv('PATH') ?: '';
    putenv('PATH='.$bin.PATH_SEPARATOR.$originalPath);

    try {
        $this->artisan('growth:ingest-local-project', [
            'path' => $repo,
            '--limit-issues' => 10,
            '--limit-prs' => 10,
            '--limit-commits' => 10,
        ])
            ->expectsOutputToContain('Imported Festival Ops')
            ->expectsOutputToContain('Requirements: 1')
            ->expectsOutputToContain('Issues: 1')
            ->expectsOutputToContain('Pull requests: 1')
            ->assertExitCode(0);
    } finally {
        putenv('PATH='.$originalPath);
    }

    $project = Project::query()->where('name', 'Festival Ops')->firstOrFail();

    expect(Source::query()->where('project_id', $project->id)->where('kind', 'ticket')->count())->toBe(2);
    expect(Requirement::query()->where('project_id', $project->id)->firstOrFail()->acceptance_criteria)
        ->toBe(['I can create ticket types', 'I can inspect sales totals']);
    expect(WorkItem::query()->where('project_id', $project->id)->where('name', '#42 Build ticketing')->firstOrFail()->status)
        ->toBe('todo');
    expect(WorkItem::query()->where('project_id', $project->id)->count())->toBe(1);
    expect(WorkItemDeliveryLink::query()->where('type', 'commit')->pluck('ref')->all())->toContain('MERGE_SHA');
    expect(WorkItemDeliveryLink::query()->where('type', 'pull_request')->firstOrFail()->workItem->name)->toBe('#42 Build ticketing');
    expect($project->projectPlan->deliverables_summary)->toContain('Ship ticketing', 'Reconcile reports');
});
