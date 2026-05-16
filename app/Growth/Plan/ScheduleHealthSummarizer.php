<?php

namespace App\Growth\Plan;

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Carbon;

class ScheduleHealthSummarizer
{
    /**
     * @return array<string,mixed>
     */
    public function summarize(Project $project): array
    {
        $today = now()->startOfDay();
        $findings = [];

        foreach ($project->milestones()->get() as $milestone) {
            if ($milestone->target_date && $milestone->status === 'pending' && $milestone->target_date->lt($today)) {
                $findings[] = $this->finding(
                    'schedule.milestone.overdue',
                    'error',
                    'Milestone target date has passed',
                    'milestone',
                    $milestone->id,
                    ['target_date' => $milestone->target_date->toDateString()],
                );
            }
        }

        $workItems = $project->workItems()
            ->with('dependencies:id,name,status,due_date')
            ->get();

        foreach ($workItems as $item) {
            if ($this->isOpen($item) && $item->due_date && $item->due_date->lt($today)) {
                $findings[] = $this->finding(
                    'schedule.work_item.overdue',
                    'error',
                    'Work item due date has passed',
                    'work_item',
                    $item->id,
                    ['due_date' => $item->due_date->toDateString()],
                );
            }

            foreach ($item->dependencies as $dependency) {
                if ($item->status === 'in_progress' && $this->isOpen($dependency)) {
                    $findings[] = $this->finding(
                        'schedule.dependency.open',
                        'warning',
                        'Work item is in progress while a dependency is unfinished',
                        'work_item',
                        $item->id,
                        ['depends_on_id' => $dependency->id],
                    );
                }

                if ($this->hasDependencyDateRisk($item, $dependency)) {
                    $findings[] = $this->finding(
                        'schedule.dependency.date_risk',
                        'warning',
                        'Work item starts before its dependency is due',
                        'work_item',
                        $item->id,
                        [
                            'planned_start_date' => $item->planned_start_date?->toDateString(),
                            'dependency_due_date' => $dependency->due_date?->toDateString(),
                            'depends_on_id' => $dependency->id,
                        ],
                    );
                }
            }
        }

        return [
            'project_id' => $project->id,
            'summary' => [
                'overdue_milestones' => $this->countRule($findings, 'schedule.milestone.overdue'),
                'overdue_work_items' => $this->countRule($findings, 'schedule.work_item.overdue'),
                'open_dependency_blocks' => $this->countRule($findings, 'schedule.dependency.open'),
                'dependency_date_risks' => $this->countRule($findings, 'schedule.dependency.date_risk'),
            ],
            'findings' => $findings,
        ];
    }

    private function isOpen(WorkItem $item): bool
    {
        return ! in_array($item->status, ['done', 'cancelled'], true);
    }

    private function hasDependencyDateRisk(WorkItem $item, WorkItem $dependency): bool
    {
        return $this->isOpen($item)
            && $this->isOpen($dependency)
            && $item->planned_start_date instanceof Carbon
            && $dependency->due_date instanceof Carbon
            && $dependency->due_date->gt($item->planned_start_date);
    }

    /**
     * @param  list<array<string,mixed>>  $findings
     */
    private function countRule(array $findings, string $rule): int
    {
        return count(array_filter($findings, fn (array $finding): bool => $finding['rule'] === $rule));
    }

    /**
     * @param  array<string,mixed>  $meta
     * @return array<string,mixed>
     */
    private function finding(string $rule, string $severity, string $message, string $subjectType, string $subjectId, array $meta = []): array
    {
        return compact('rule', 'severity', 'message') + [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta,
        ];
    }
}
