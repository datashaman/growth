<?php

namespace App\Growth\Lint;

use App\Models\ChangeRequest;
use App\Models\Project;

/**
 * Change-control completeness checks.
 *
 * Each finding: array{rule, severity, message, subject_type, subject_id}.
 */
class ChangeLinter
{
    /**
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    public function check(Project $project): array
    {
        $findings = [];

        $changes = $project->changeRequests()
            ->with(['impacts', 'review'])
            ->get();

        foreach ($changes as $change) {
            array_push($findings, ...$this->checkChange($change, $project->rigor_level));
        }

        return $findings;
    }

    /**
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    private function checkChange(ChangeRequest $change, int $rigorLevel): array
    {
        $findings = [];

        if ($change->impacts->isEmpty()) {
            $findings[] = $this->finding(
                'change.impacts.empty',
                'error',
                'Change request has no impacted artifacts',
                'change_request',
                $change->id,
            );
        }

        // Linking a change to a formal review is an L3+ control. At lower rigor
        // an approved change request alone satisfies change control, so this
        // does not drag in the full review → participant → role machinery.
        if ($rigorLevel >= 3 && in_array($change->status, ['under_review', 'approved', 'implemented'], true) && ! $change->review_id) {
            $findings[] = $this->finding(
                'change.review.missing',
                'warning',
                "Change request is {$change->status} but has no linked review",
                'change_request',
                $change->id,
            );
        }

        if (in_array($change->status, ['approved', 'rejected', 'deferred', 'implemented'], true) && ! $change->decision) {
            $findings[] = $this->finding(
                'change.decision.missing',
                'error',
                "Change request is {$change->status} but has no decision",
                'change_request',
                $change->id,
            );
        }

        if ($change->decision && trim((string) $change->decision_rationale) === '') {
            $findings[] = $this->finding(
                'change.decision_rationale.empty',
                'warning',
                'Change request has a decision without rationale',
                'change_request',
                $change->id,
            );
        }

        if ($change->status === 'implemented' && $change->decision !== 'approved') {
            $findings[] = $this->finding(
                'change.implemented_without_approval',
                'error',
                'Change request is implemented without an approved decision',
                'change_request',
                $change->id,
            );
        }

        foreach ($change->impacts->where('impact_kind', 'needs_analysis') as $impact) {
            $findings[] = $this->finding(
                'change.impact.needs_analysis',
                'warning',
                'Change request has an unresolved impact-analysis item',
                'change_impact',
                $impact->id,
            );
        }

        if (in_array($change->priority, ['high', 'critical'], true) && trim((string) $change->rationale) === '') {
            $findings[] = $this->finding(
                'change.rationale.empty',
                'warning',
                'High-priority change request has no rationale',
                'change_request',
                $change->id,
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
