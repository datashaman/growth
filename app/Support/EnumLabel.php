<?php

namespace App\Support;

/**
 * Human-readable labels for the snake_case / dotted enum values
 * we persist in the database and surface in the UI.
 *
 * Use `title()` for the common snake/dot/kebab → "Sentence case"
 * conversion. Add a curated method when the natural conversion
 * produces something awkward (e.g. acronyms, domain phrasing).
 */
class EnumLabel
{
    public static function title(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return ucfirst(str_replace(['_', '.', '-'], ' ', $value));
    }

    /**
     * Lowercase badge variant — `under_review` → "under review".
     * The webapp's badge convention is sentence-lowercased values.
     */
    public static function lower(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return str_replace(['_', '.', '-'], ' ', $value);
    }

    public static function gate(string $id): string
    {
        return match ($id) {
            'capabilities' => 'Capabilities',
            'architecture' => 'Architecture',
            'verification' => 'Verification',
            'planning' => 'Planning',
            'review' => 'Review',
            'change_control' => 'Change control',
            'implementation' => 'Implementation',
            default => self::title($id),
        };
    }

    public static function findingRule(string $rule): string
    {
        return match ($rule) {
            'schedule.milestone.overdue' => 'Milestone overdue',
            'schedule.work_item.overdue' => 'Work item overdue',
            'schedule.dependency.open' => 'Open dependency',
            'schedule.dependency.date_risk' => 'Dependency date risk',
            'pmp.milestone.no_date' => 'Milestone missing target date',
            'pmp.milestone.past_pending' => 'Milestone past target date',
            'pmp.milestone.could_hit' => 'Milestone could be marked hit',
            'pmp.milestone.could_miss' => 'Milestone likely missed',
            'pmp.milestones.empty' => 'No milestones',
            'pmp.missing' => 'PMP missing',
            'pmp.scope.empty' => 'PMP scope empty',
            'pmp.approach.empty' => 'PMP approach empty',
            'pmp.wbs.empty' => 'WBS empty',
            'pmp.wbs.flat' => 'WBS flat',
            'pmp.wbs.cycle' => 'WBS dependency cycle',
            'pmp.work_item.no_role' => 'Work item without responsible role',
            'pmp.roles.empty' => 'No roles defined',
            'pmp.requirement.uncovered' => 'Uncovered requirement',
            'pmp.risk.high_unmitigated' => 'Unmitigated high-exposure risk',
            'pmp.schedule.milestone.overdue' => 'Milestone overdue',
            'pmp.schedule.work_item.overdue' => 'Work item overdue',
            'pmp.schedule.dependency.open' => 'Open dependency',
            'pmp.schedule.dependency.date_risk' => 'Dependency date risk',
            'baseline.none' => 'No plan baseline',
            'baseline.artifact.removed' => 'Baseline artifact removed',
            'baseline.drift.uncovered' => 'Baseline drift without change coverage',
            'implementation.checks.failed' => 'Failed checks',
            'implementation.done_without_evidence' => 'Done without delivery evidence',
            default => self::title($rule),
        };
    }
}
