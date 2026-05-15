<?php

namespace App\Growth\Lint;

use App\Models\DesignView;
use App\Models\Project;

/**
 * Architecture completeness checks.
 *
 * Each finding: array{rule, severity, message, subject_type, subject_id}.
 */
class DesignLinter
{
    /**
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    public function check(Project $project): array
    {
        $findings = [];

        $views = $project->designViews()->with(['elements', 'concerns'])->get();

        foreach ($views as $view) {
            if ($view->elements->isEmpty()) {
                $findings[] = $this->finding(
                    'view-empty', 'warning',
                    'Design view has no elements',
                    'design_view', $view->id,
                );
            }
            if ($view->concerns->isEmpty()) {
                $findings[] = $this->finding(
                    'view-no-concerns', 'warning',
                    'Design view does not address any stakeholder concerns',
                    'design_view', $view->id,
                );
            }
        }

        $usedViewpoints = $views->pluck('viewpoint')->unique();
        $customs = $project->customViewpoints()->get();
        foreach ($customs as $cv) {
            if (! $usedViewpoints->contains($cv->name)) {
                $findings[] = $this->finding(
                    'viewpoint-unused', 'warning',
                    'Custom viewpoint is defined but no view instantiates it',
                    'custom_viewpoint', $cv->id,
                );
            }
        }

        $unusedViewpoints = collect(DesignView::BUILTIN_VIEWPOINTS)
            ->diff($usedViewpoints)
            ->values();
        $concernCount = $project->concerns()->count();
        if ($views->isEmpty() && $concernCount > 0) {
            $findings[] = $this->finding(
                'no-views', 'error',
                'Project has concerns but no design views — concerns unaddressed',
                'project', $project->id,
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
}
