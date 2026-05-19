<?php

namespace App\Growth\Lint;

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
                'Project has no Project Management Plan',
                'project', $project->id,
            );
        } else {
            if (trim((string) $plan->scope_summary) === '') {
                $findings[] = $this->finding(
                    'pmp.scope.empty', 'error',
                    'PMP has no scope_summary',
                    'project_plan', $plan->id,
                );
            }
            if (trim((string) $plan->approach) === '') {
                $findings[] = $this->finding(
                    'pmp.approach.empty', 'warning',
                    'PMP has no approach',
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
                'Project has no milestones',
                'project', $project->id,
            );
        }
        foreach ($milestones as $m) {
            $items = $m->workItems;
            if ($m->status === 'pending' && $items->isNotEmpty()) {
                $live = $items->whereNotIn('status', ['done', 'cancelled']);
                if ($live->isEmpty()) {
                    $findings[] = $this->finding(
                        'pmp.milestone.could_achieve', 'warning',
                        'Milestone has every linked work item done/cancelled — consider flipping status to achieved',
                        'milestone', $m->id,
                    );
                }
            }
        }

        $workItems = $project->workItems()
            ->with(['requirements:id', 'dependencies:id,status'])
            ->get();
        if ($workItems->isEmpty() && $il >= 2) {
            $findings[] = $this->finding(
                'pmp.wbs.empty', 'error',
                'Project has no work items',
                'project', $project->id,
            );
        } elseif ($workItems->count() > 5 && $workItems->whereNotNull('parent_id')->isEmpty()) {
            $findings[] = $this->finding(
                'pmp.wbs.flat', 'warning',
                'WBS is flat — all work items are roots',
                'project', $project->id,
            );
        }

        foreach ($this->detectDependencyCycles($workItems) as $cycleNode) {
            $findings[] = $this->finding(
                'pmp.wbs.cycle', 'error',
                'Work item participates in a dependency cycle',
                'work_item', $cycleNode->id,
            );
        }

        foreach ($workItems as $w) {
            if ($w->status !== 'in_progress') {
                continue;
            }
            foreach ($w->dependencies as $dependency) {
                if (! in_array($dependency->status, ['done', 'cancelled'], true)) {
                    $findings[] = $this->finding(
                        'pmp.dependency.open', 'warning',
                        'Work item is in progress while a dependency is unfinished',
                        'work_item', $w->id,
                    );
                }
            }
        }

        if ($il >= 3) {
            foreach ($workItems as $w) {
                if (! $w->responsible_role_id) {
                    $findings[] = $this->finding(
                        'pmp.work_item.no_role', 'warning',
                        "Work item has no responsible role at Rigor level {$il}",
                        'work_item', $w->id,
                    );
                }
            }
            if ($project->roles()->count() === 0) {
                $findings[] = $this->finding(
                    'pmp.roles.empty', 'warning',
                    'Project has no roles defined at Rigor level '.$il,
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
                'High-priority requirement is not covered by any work item',
                'requirement', $r->id,
            );
        }

        $uiMissingMockup = $project->requirements()
            ->where('renders_ui', true)
            ->whereHas('workItems')
            ->whereDoesntHave('workItems', fn ($q) => $q->where('needs_mockups', true))
            ->get(['id']);
        foreach ($uiMissingMockup as $r) {
            $findings[] = $this->finding(
                'pmp.requirement.ui_no_mockup', 'informational',
                'UI-bearing requirement is covered, but none of its work items needs a mockup',
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
                'High-exposure risk has no mitigation plan',
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
