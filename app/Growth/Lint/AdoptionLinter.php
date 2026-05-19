<?php

namespace App\Growth\Lint;

use App\Models\Project;

/**
 * Internal artifact-coverage checks for adopted projects.
 *
 * Reports artifact-to-artifact links missing within Growth's own model so a
 * human gets a punch-list of reconstruction work outstanding after a repo is
 * adopted. The section informs; it never gates.
 *
 * Findings are emitted only for adopted projects (`adopted_at` set). Every
 * finding carries severity `informational` — an adoption gap on a
 * freshly-adopted repo is expected, not wrong.
 *
 * Each finding: array{rule, severity, message, subject_type, subject_id}.
 */
class AdoptionLinter
{
    /**
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    public function check(Project $project): array
    {
        if ($project->adopted_at === null) {
            return [];
        }

        $findings = [];

        $requirements = $project->requirements()
            ->with(['workItems', 'testCases'])
            ->get();

        foreach ($requirements as $requirement) {
            if ($requirement->workItems->isEmpty()) {
                $findings[] = $this->finding(
                    'adoption.requirement.no_work_item',
                    'Requirement has no linked work item',
                    'requirement', $requirement->id,
                );
            }
            if ($requirement->testCases->isEmpty()) {
                $findings[] = $this->finding(
                    'adoption.requirement.no_verification',
                    'Requirement has no verification case',
                    'requirement', $requirement->id,
                );
            }
        }

        $workItems = $project->workItems()->with('requirements')->get();

        foreach ($workItems as $workItem) {
            if ($workItem->requirements->isEmpty()) {
                $findings[] = $this->finding(
                    'adoption.work_item.no_requirement',
                    'Work item has no linked requirement',
                    'work_item', $workItem->id,
                );
            }
        }

        if ($requirements->isEmpty()) {
            $findings[] = $this->finding(
                'adoption.project.no_requirements',
                'Adopted project has no requirements',
                'project', $project->id,
            );
        }

        if ($project->designViews()->whereHas('elements')->doesntExist()) {
            $findings[] = $this->finding(
                'adoption.project.no_architecture',
                'Adopted project has no design elements',
                'project', $project->id,
            );
        }

        return $findings;
    }

    /**
     * @return array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}
     */
    private function finding(string $rule, string $message, string $subjectType, string $subjectId): array
    {
        return [
            'rule' => $rule,
            'severity' => 'informational',
            'message' => $message,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ];
    }
}
