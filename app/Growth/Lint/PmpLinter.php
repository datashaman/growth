<?php

namespace App\Growth\Lint;

use App\Growth\Plan\ScheduleHealthSummarizer;
use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Collection;

/**
 * Delivery planning completeness checks.
 *
 * Rules scale with rigor level. Each finding: array{rule, severity, message, subject_type, subject_id}.
 */
class PmpLinter
{
    public function __construct(private readonly ScheduleHealthSummarizer $scheduleHealth) {}

    /**
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    public function check(Project $project): array
    {
        $findings = [];
        $il = $project->rigor_level;

        $plan = $project->projectPlan;
        if (! $plan) {
            $findings[] = $this->finding(
                'pmp.missing', 'error',
                'rule: project has no Project Management Plan',
                'project', $project->id,
            );
        } else {
            if (trim((string) $plan->scope_summary) === '') {
                $findings[] = $this->finding(
                    'pmp.scope.empty', 'error',
                    'rule: PMP has no scope_summary',
                    'project_plan', $plan->id,
                );
            }
            if (trim((string) $plan->approach) === '') {
                $findings[] = $this->finding(
                    'pmp.approach.empty', 'warning',
                    'rule: PMP has no approach',
                    'project_plan', $plan->id,
                );
            }
        }

        $milestones = $project->milestones()
            ->with('workItems:id,status')
            ->get();
        if ($milestones->isEmpty() && $il >= 2) {
            $findings[] = $this->finding(
                'pmp.milestones.empty', 'error',
                'rule: project has no milestones',
                'project', $project->id,
            );
        }
        $today = now()->startOfDay();
        foreach ($milestones as $m) {
            if (! $m->target_date) {
                $findings[] = $this->finding(
                    'pmp.milestone.no_date', 'warning',
                    "rule: milestone [{$m->name}] has no target_date",
                    'milestone', $m->id,
                );
            } elseif ($m->status === 'pending' && $m->target_date->lt($today)) {
                $findings[] = $this->finding(
                    'pmp.milestone.past_pending', 'error',
                    "rule: milestone [{$m->name}] target_date {$m->target_date->toDateString()} is in the past and still pending",
                    'milestone', $m->id,
                );
            }

            $items = $m->workItems;
            if ($m->status === 'pending' && $items->isNotEmpty()) {
                $live = $items->whereNotIn('status', ['done', 'cancelled']);
                if ($live->isEmpty()) {
                    $findings[] = $this->finding(
                        'pmp.milestone.could_hit', 'warning',
                        "milestone [{$m->name}] has every linked work item done/cancelled — consider flipping status to hit",
                        'milestone', $m->id,
                    );
                } elseif ($m->target_date && $m->target_date->lt($today)) {
                    $findings[] = $this->finding(
                        'pmp.milestone.could_miss', 'warning',
                        "milestone [{$m->name}] target_date passed with {$live->count()} work item(s) still open — likely missed",
                        'milestone', $m->id,
                    );
                }
            }
        }

        $workItems = $project->workItems()
            ->with(['requirements:id', 'dependencies:id'])
            ->get();
        if ($workItems->isEmpty() && $il >= 2) {
            $findings[] = $this->finding(
                'pmp.wbs.empty', 'error',
                'rule: project has no work items',
                'project', $project->id,
            );
        } elseif ($workItems->count() > 5 && $workItems->whereNotNull('parent_id')->isEmpty()) {
            $findings[] = $this->finding(
                'pmp.wbs.flat', 'warning',
                'rule: WBS is flat — all work items are roots',
                'project', $project->id,
            );
        }

        foreach ($this->detectDependencyCycles($workItems) as $cycleNode) {
            $findings[] = $this->finding(
                'pmp.wbs.cycle', 'error',
                "rule: work item [{$cycleNode->name}] participates in a dependency cycle",
                'work_item', $cycleNode->id,
            );
        }

        foreach ($this->scheduleHealth->summarize($project)['findings'] as $scheduleFinding) {
            $findings[] = $this->finding(
                str_replace('schedule.', 'pmp.schedule.', $scheduleFinding['rule']),
                $scheduleFinding['severity'],
                $scheduleFinding['message'],
                $scheduleFinding['subject_type'],
                $scheduleFinding['subject_id'],
            );
        }

        if ($il >= 3) {
            foreach ($workItems as $w) {
                if (! $w->responsible_role_id) {
                    $findings[] = $this->finding(
                        'pmp.work_item.no_role', 'warning',
                        "rule: work item [{$w->name}] has no responsible role at Rigor level {$il}",
                        'work_item', $w->id,
                    );
                }
            }
            if ($project->roles()->count() === 0) {
                $findings[] = $this->finding(
                    'pmp.roles.empty', 'warning',
                    'rule: project has no roles defined at Rigor level '.$il,
                    'project', $project->id,
                );
            }
        }

        $uncoveredHigh = $project->requirements()
            ->where('priority', 'high')
            ->doesntHave('workItems')
            ->get(['id', 'text']);
        foreach ($uncoveredHigh as $r) {
            $findings[] = $this->finding(
                'pmp.requirement.uncovered', 'warning',
                "rule: high-priority requirement [{$r->id}] is not covered by any work item",
                'requirement', $r->id,
            );
        }

        $highUnmitigatedRisks = $project->risks()
            ->where('probability', 'high')
            ->where('impact', 'high')
            ->whereIn('status', ['identified', 'assessed'])
            ->where(fn ($q) => $q->whereNull('mitigation_plan')->orWhere('mitigation_plan', ''))
            ->get(['id', 'title']);
        foreach ($highUnmitigatedRisks as $risk) {
            $findings[] = $this->finding(
                'pmp.risk.high_unmitigated', 'error',
                "risk management: high-exposure risk [{$risk->title}] has no mitigation plan",
                'risk', $risk->id,
            );
        }

        return $findings;
    }

    /**
     * @return array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}
     */
    private function finding(string $rule, string $severity, string $message, string $subjectType, string $subjectId): array
    {
        return compact('rule', 'severity', 'message') + [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ];
    }

    /**
     * @return Collection<int,WorkItem>
     */
    private function detectDependencyCycles(Collection $workItems): Collection
    {
        $byId = $workItems->keyBy('id');
        $state = [];
        $stack = [];
        $cycleIds = [];

        $visit = function (string $id) use (&$visit, &$state, &$stack, &$cycleIds, $byId): void {
            $state[$id] = 'grey';
            $stack[] = $id;

            $item = $byId->get($id);
            if (! $item) {
                array_pop($stack);
                $state[$id] = 'black';

                return;
            }

            foreach ($item->dependencies as $dependency) {
                $dependencyId = $dependency->id;

                if (! $byId->has($dependencyId)) {
                    continue;
                }

                if (($state[$dependencyId] ?? 'white') === 'white') {
                    $visit($dependencyId);

                    continue;
                }

                if ($state[$dependencyId] === 'grey') {
                    $cycleStart = array_search($dependencyId, $stack, true);
                    if ($cycleStart !== false) {
                        foreach (array_slice($stack, $cycleStart) as $cycleId) {
                            $cycleIds[$cycleId] = true;
                        }
                    }
                }
            }

            array_pop($stack);
            $state[$id] = 'black';
        };

        foreach ($workItems as $item) {
            if (($state[$item->id] ?? 'white') === 'white') {
                $visit($item->id);
            }
        }

        return $workItems
            ->filter(fn ($item) => isset($cycleIds[$item->id]))
            ->values();
    }
}
