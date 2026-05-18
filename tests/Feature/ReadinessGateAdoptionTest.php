<?php

use App\Growth\Assurance\ReadinessGateEvaluator;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->evaluator = app(ReadinessGateEvaluator::class);

    /**
     * Pull the `done_without_evidence` finding for a work item out of an
     * evaluated project's implementation gate.
     */
    $this->evidenceGap = function (Project $project, WorkItem $workItem): ?array {
        $gates = collect($this->evaluator->evaluate($project)['gates']);
        $findings = $gates->firstWhere('id', 'implementation')['findings'];

        return collect($findings)->first(fn (array $finding): bool => $finding['rule'] === 'implementation.done_without_evidence'
            && $finding['subject_id'] === $workItem->id);
    };

    /**
     * Create a `done` work item with no delivery evidence, optionally
     * recording a witnessed completion at the given time.
     */
    $this->doneWorkItem = function (Project $project, ?string $completedAt = null): WorkItem {
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'kind' => 'task',
            'name' => 'Shipped feature',
            'status' => 'done',
        ]);

        if ($completedAt !== null) {
            $workItem->statusTransitions()->create([
                'from_status' => 'in_progress',
                'to_status' => 'done',
                'transitioned_at' => $completedAt,
            ]);
        }

        return $workItem;
    };
});

it('reports a done-without-evidence gap as a warning when the project has no adoption date', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Greenfield',
        'rigor_level' => 2,
    ]);
    $workItem = ($this->doneWorkItem)($project);

    expect(($this->evidenceGap)($project, $workItem)['severity'])->toBe('warning');
});

it('reports a pre-adoption gap as informational', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Brownfield',
        'rigor_level' => 2,
        'adopted_at' => now(),
    ]);
    $workItem = ($this->doneWorkItem)($project, now()->subDays(30)->toDateTimeString());

    expect(($this->evidenceGap)($project, $workItem)['severity'])->toBe('informational');
});

it('treats a retro-created done item with no completion trail as pre-adoption', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Brownfield',
        'rigor_level' => 2,
        'adopted_at' => now(),
    ]);
    $workItem = ($this->doneWorkItem)($project);

    expect(($this->evidenceGap)($project, $workItem)['severity'])->toBe('informational');
});

it('reports a post-adoption gap as a warning', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Brownfield',
        'rigor_level' => 2,
        'adopted_at' => now()->subDays(30),
    ]);
    $workItem = ($this->doneWorkItem)($project, now()->toDateTimeString());

    expect(($this->evidenceGap)($project, $workItem)['severity'])->toBe('warning');
});

it('treats completion exactly at the adoption instant as post-adoption', function () {
    $adoptedAt = now()->subDays(7)->startOfSecond();
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Brownfield',
        'rigor_level' => 2,
        'adopted_at' => $adoptedAt,
    ]);
    $workItem = ($this->doneWorkItem)($project, $adoptedAt->toDateTimeString());

    expect(($this->evidenceGap)($project, $workItem)['severity'])->toBe('warning');
});

it('keeps an informational gap from moving the implementation gate off pass', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Brownfield',
        'rigor_level' => 2,
        'adopted_at' => now(),
    ]);
    ($this->doneWorkItem)($project, now()->subDays(30)->toDateTimeString());

    $gate = collect($this->evaluator->evaluate($project)['gates'])->firstWhere('id', 'implementation');

    expect($gate['status'])->toBe('pass')
        ->and($gate['warnings'])->toBe(0);
});
