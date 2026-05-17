<?php

use App\Growth\Assurance\MilestoneGateEvaluator;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\AchieveMilestone;
use App\Mcp\Tools\Plan\ListMilestones;
use App\Models\CheckRunEvidence;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Gated',
        'rigor_level' => 2,
    ]);

    $this->milestone = Milestone::create([
        'project_id' => $this->project->id,
        'name' => 'Beta',
        'status' => 'pending',
    ]);

    // Link a work item to the milestone, returning it for further setup.
    $this->addWorkItem = function (string $status): WorkItem {
        $workItem = WorkItem::create([
            'project_id' => $this->project->id,
            'kind' => WorkItem::KINDS[0],
            'name' => 'Work item',
            'status' => $status,
        ]);

        $this->milestone->workItems()->attach($workItem->id);

        return $workItem;
    };

    $this->gate = fn (): array => app(MilestoneGateEvaluator::class)->evaluate($this->milestone->fresh());
});

// ---- evaluator ----

it('fails the gate for a milestone with no member work items', function () {
    $gate = ($this->gate)();

    expect($gate['status'])->toBe('fail')
        ->and($gate['work_items'])->toBe(0)
        ->and($gate['findings'])->toHaveCount(1)
        ->and($gate['findings'][0]['rule'])->toBe('milestone.empty');
});

it('passes the gate when every member is done with delivery evidence', function () {
    $workItem = ($this->addWorkItem)('done');
    WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'pull_request',
        'ref' => '#1',
    ]);

    expect(($this->gate)()['status'])->toBe('pass');
});

it('fails the gate when a member work item is not done', function () {
    ($this->addWorkItem)('done');
    ($this->addWorkItem)('in_progress');

    $gate = ($this->gate)();

    expect($gate['status'])->toBe('fail')
        ->and($gate['errors'])->toBe(1)
        ->and(collect($gate['findings'])->pluck('rule'))->toContain('milestone.work_item.not_done');
});

it('fails the gate when a done member has failed checks', function () {
    $workItem = ($this->addWorkItem)('done');
    $link = WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'pull_request',
        'ref' => '#2',
    ]);
    CheckRunEvidence::create([
        'work_item_delivery_link_id' => $link->id,
        'provider' => 'github-actions',
        'name' => 'tests',
        'status' => 'completed',
        'conclusion' => 'failure',
    ]);

    $gate = ($this->gate)();

    expect($gate['status'])->toBe('fail')
        ->and(collect($gate['findings'])->pluck('rule'))->toContain('milestone.work_item.failed_checks');
});

it('warns but does not fail when a done member has no delivery evidence', function () {
    ($this->addWorkItem)('done');

    $gate = ($this->gate)();

    expect($gate['status'])->toBe('warn')
        ->and($gate['errors'])->toBe(0)
        ->and($gate['warnings'])->toBe(1)
        ->and($gate['findings'][0]['rule'])->toBe('milestone.work_item.done_without_evidence');
});

it('fails the gate when a linked member work item belongs to another project', function () {
    $otherProject = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Elsewhere',
        'rigor_level' => 2,
    ]);
    $foreignItem = WorkItem::create([
        'project_id' => $otherProject->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Foreign work item',
        'status' => 'done',
    ]);
    $this->milestone->workItems()->attach($foreignItem->id);

    $gate = ($this->gate)();

    expect($gate['status'])->toBe('fail')
        ->and($gate['errors'])->toBe(1)
        ->and(collect($gate['findings'])->pluck('rule'))->toContain('milestone.work_item.project_mismatch');
});

// ---- transition gate ----

it('rejects achieving an empty milestone', function () {
    PlanningServer::tool(AchieveMilestone::class, ['milestone_id' => $this->milestone->id])
        ->assertHasErrors(['Cannot achieve a milestone until its gate passes: it has no member work items — link the work items it bundles first.']);

    expect($this->milestone->fresh()->status)->toBe('pending')
        ->and(StatusTransition::count())->toBe(0);
});

it('rejects achieving a milestone with an unfinished member', function () {
    ($this->addWorkItem)('done');
    ($this->addWorkItem)('blocked');

    PlanningServer::tool(AchieveMilestone::class, ['milestone_id' => $this->milestone->id])
        ->assertHasErrors(['Cannot achieve a milestone until its gate passes: 1 member work item is not done.']);

    expect($this->milestone->fresh()->status)->toBe('pending');
});

it('rejects achieving a milestone whose member has failed checks', function () {
    $workItem = ($this->addWorkItem)('done');
    $link = WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'pull_request',
        'ref' => '#3',
    ]);
    CheckRunEvidence::create([
        'work_item_delivery_link_id' => $link->id,
        'provider' => 'github-actions',
        'name' => 'tests',
        'status' => 'completed',
        'conclusion' => 'failure',
    ]);

    PlanningServer::tool(AchieveMilestone::class, ['milestone_id' => $this->milestone->id])
        ->assertHasErrors(['Cannot achieve a milestone until its gate passes: 1 done member work item has failed checks.']);

    expect($this->milestone->fresh()->status)->toBe('pending');
});

it('rejects achieving a milestone with a cross-project member', function () {
    $otherProject = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Elsewhere',
        'rigor_level' => 2,
    ]);
    $foreignItem = WorkItem::create([
        'project_id' => $otherProject->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Foreign work item',
        'status' => 'done',
    ]);
    $this->milestone->workItems()->attach($foreignItem->id);

    PlanningServer::tool(AchieveMilestone::class, ['milestone_id' => $this->milestone->id])
        ->assertHasErrors(['Cannot achieve a milestone until its gate passes: 1 member work item belongs to a different project.']);

    expect($this->milestone->fresh()->status)->toBe('pending');
});

it('achieves a milestone whose gate only warns', function () {
    ($this->addWorkItem)('done');

    PlanningServer::tool(AchieveMilestone::class, ['milestone_id' => $this->milestone->id])
        ->assertOk();

    expect($this->milestone->fresh()->status)->toBe('achieved');
});

// ---- list-milestones gate field ----

it('exposes each milestone\'s gate on list-milestones', function () {
    ($this->addWorkItem)('in_progress');

    PlanningServer::tool(ListMilestones::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('results.0.gate.status', 'fail')
                ->where('results.0.gate.errors', 1)
                ->where('results.0.gate.warnings', 0)
                ->etc();
        });
});

it('evaluates every milestone gate on list-milestones without an N+1 query', function () {
    // Three milestones, each bundling a done work item with delivery evidence
    // and a check run — enough relations to expose lazy loading per milestone.
    foreach (range(1, 3) as $n) {
        $milestone = Milestone::create([
            'project_id' => $this->project->id,
            'name' => "Release {$n}",
            'status' => 'pending',
        ]);

        $workItem = WorkItem::create([
            'project_id' => $this->project->id,
            'kind' => WorkItem::KINDS[0],
            'name' => "Work item {$n}",
            'status' => 'done',
        ]);
        $milestone->workItems()->attach($workItem->id);

        $link = WorkItemDeliveryLink::create([
            'work_item_id' => $workItem->id,
            'type' => 'pull_request',
            'ref' => "#{$n}",
        ]);
        CheckRunEvidence::create([
            'work_item_delivery_link_id' => $link->id,
            'provider' => 'github-actions',
            'name' => 'tests',
            'status' => 'completed',
            'conclusion' => 'success',
        ]);
    }

    DB::connection()->enableQueryLog();

    PlanningServer::tool(ListMilestones::class, ['project_id' => $this->project->id])
        ->assertOk();

    $listQueries = count(DB::connection()->getQueryLog());

    // Add three more milestones with the same shape; the query count for the
    // list must not grow with the number of milestones being evaluated.
    foreach (range(4, 6) as $n) {
        $milestone = Milestone::create([
            'project_id' => $this->project->id,
            'name' => "Release {$n}",
            'status' => 'pending',
        ]);

        $workItem = WorkItem::create([
            'project_id' => $this->project->id,
            'kind' => WorkItem::KINDS[0],
            'name' => "Work item {$n}",
            'status' => 'done',
        ]);
        $milestone->workItems()->attach($workItem->id);

        $link = WorkItemDeliveryLink::create([
            'work_item_id' => $workItem->id,
            'type' => 'pull_request',
            'ref' => "#{$n}",
        ]);
        CheckRunEvidence::create([
            'work_item_delivery_link_id' => $link->id,
            'provider' => 'github-actions',
            'name' => 'tests',
            'status' => 'completed',
            'conclusion' => 'success',
        ]);
    }

    DB::connection()->flushQueryLog();

    PlanningServer::tool(ListMilestones::class, ['project_id' => $this->project->id])
        ->assertOk();

    expect(count(DB::connection()->getQueryLog()))->toBe($listQueries);
});
