<?php

namespace App\Growth\Lint;

use App\Growth\Reviews\RequirementReviewCoverage;
use App\Models\Project;
use App\Models\Review;
use App\Models\ReviewFinding;
use App\Models\ReviewPlan;

/**
 * review review record completeness and closure checks.
 *
 * Each finding: array{rule, severity, message, subject_type, subject_id}.
 */
class ReviewLinter
{
    public function __construct(private readonly RequirementReviewCoverage $requirementCoverage) {}

    /**
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    public function check(Project $project): array
    {
        $findings = [];

        $reviews = $project->reviews()
            ->with(['reviewPlan', 'targets', 'findings', 'participants'])
            ->get();

        foreach ($project->reviewPlans()->get() as $plan) {
            array_push($findings, ...$this->checkPlan($plan));
        }

        if ($project->integrity_level >= 3) {
            foreach ($project->requirements()->get() as $requirement) {
                $coverage = $this->requirementCoverage->summarize($requirement);
                if (! $coverage['covered']) {
                    $findings[] = $this->finding(
                        'requirement.review.missing',
                        'warning',
                        "review readiness: requirement [{$requirement->id}] has no closed accepted review",
                        'requirement',
                        $requirement->id,
                    );
                }
                if ($coverage['open_finding_count'] > 0) {
                    $findings[] = $this->finding(
                        'requirement.review.findings_open',
                        'warning',
                        "review readiness: requirement [{$requirement->id}] has {$coverage['open_finding_count']} open review finding(s)",
                        'requirement',
                        $requirement->id,
                    );
                }
            }
        }

        if ($project->integrity_level >= 3 && $reviews->isEmpty()) {
            $findings[] = $this->finding(
                'review.none',
                'warning',
                "review readiness: Rigor level {$project->integrity_level} project has no recorded reviews or audits",
                'project',
                $project->id,
            );
        }

        foreach ($reviews as $review) {
            array_push($findings, ...$this->checkReview($review));
        }

        return $findings;
    }

    /**
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    private function checkPlan(ReviewPlan $plan): array
    {
        $findings = [];

        if (trim((string) $plan->procedure) === '') {
            $findings[] = $this->finding(
                'review_plan.procedure.empty',
                'warning',
                "review readiness: review plan [{$plan->name}] has no procedure",
                'review_plan',
                $plan->id,
            );
        }

        if (empty($plan->entry_criteria)) {
            $findings[] = $this->finding(
                'review_plan.entry_criteria.empty',
                'warning',
                "review readiness: review plan [{$plan->name}] has no entry criteria",
                'review_plan',
                $plan->id,
            );
        }

        if (empty($plan->exit_criteria)) {
            $findings[] = $this->finding(
                'review_plan.exit_criteria.empty',
                'warning',
                "review readiness: review plan [{$plan->name}] has no exit criteria",
                'review_plan',
                $plan->id,
            );
        }

        if (empty($plan->expected_responsibilities)) {
            $findings[] = $this->finding(
                'review_plan.responsibilities.empty',
                'warning',
                "review readiness: review plan [{$plan->name}] has no expected responsibilities",
                'review_plan',
                $plan->id,
            );
        }

        return $findings;
    }

    /**
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    private function checkReview(Review $review): array
    {
        $findings = [];

        if ($review->targets->isEmpty()) {
            $findings[] = $this->finding(
                'review.targets.empty',
                'error',
                "review readiness: review [{$review->title}] has no target artifacts",
                'review',
                $review->id,
            );
        }

        if (in_array($review->status, ['planned', 'in_progress'], true) && empty($review->entry_criteria)) {
            $findings[] = $this->finding(
                'review.entry_criteria.empty',
                'warning',
                "review readiness: review [{$review->title}] has no entry criteria",
                'review',
                $review->id,
            );
        }

        if ($review->participants->isEmpty()) {
            $findings[] = $this->finding(
                'review.participants.empty',
                'warning',
                "review readiness: review [{$review->title}] has no participant roles recorded",
                'review',
                $review->id,
            );
        }

        if ($review->reviewPlan && $review->reviewPlan->expected_responsibilities) {
            foreach ($review->reviewPlan->expected_responsibilities as $responsibility) {
                if (! $review->participants->contains('responsibility', $responsibility)) {
                    $findings[] = $this->finding(
                        'review.plan_role_missing',
                        'warning',
                        "review readiness: review [{$review->title}] is missing expected {$responsibility} participant from its review plan",
                        'review',
                        $review->id,
                    );
                }
            }
        }

        if ($review->type === 'inspection') {
            foreach (['moderator', 'reviewer', 'recorder'] as $responsibility) {
                if (! $review->participants->contains('responsibility', $responsibility)) {
                    $findings[] = $this->finding(
                        'review.inspection.role_missing',
                        'warning',
                        "review readiness: inspection [{$review->title}] has no {$responsibility} participant",
                        'review',
                        $review->id,
                    );
                }
            }
        }

        if (in_array($review->status, ['held', 'closed'], true)) {
            $absentRequired = $review->participants
                ->whereIn('responsibility', ['moderator', 'reviewer', 'recorder', 'auditor'])
                ->where('attendance_status', 'absent');
            foreach ($absentRequired as $participant) {
                $findings[] = $this->finding(
                    'review.participant.absent_required',
                    'warning',
                    "review readiness: {$participant->responsibility} participant was absent from review [{$review->title}]",
                    'review_participant',
                    $participant->id,
                );
            }
        }

        if (in_array($review->status, ['held', 'closed'], true) && empty($review->exit_criteria)) {
            $findings[] = $this->finding(
                'review.exit_criteria.empty',
                'warning',
                "review readiness: review [{$review->title}] has no exit criteria",
                'review',
                $review->id,
            );
        }

        if (in_array($review->status, ['held', 'closed'], true) && ! $review->decision) {
            $findings[] = $this->finding(
                'review.decision.missing',
                'error',
                "review readiness: review [{$review->title}] is {$review->status} but has no decision",
                'review',
                $review->id,
            );
        }

        if ($review->status === 'closed' && in_array($review->decision, ['accepted', 'accepted_with_actions'], true)) {
            $signedApprover = $review->participants
                ->where('responsibility', 'approver')
                ->whereNotNull('signed_off_at')
                ->isNotEmpty();

            if (! $signedApprover) {
                $findings[] = $this->finding(
                    'review.approver.signoff_missing',
                    'warning',
                    "review readiness: closed review [{$review->title}] has no approver signoff",
                    'review',
                    $review->id,
                );
            }
        }

        $unresolvedSevere = $review->findings
            ->whereIn('severity', ['high', 'critical'])
            ->whereNotIn('status', ['resolved', 'accepted', 'closed']);
        foreach ($unresolvedSevere as $finding) {
            $findings[] = $this->finding(
                'review.finding.severe_unresolved',
                'error',
                "review readiness: {$finding->severity} finding [{$finding->title}] is still {$finding->status}",
                'review_finding',
                $finding->id,
            );
        }

        if ($review->status === 'closed') {
            $openFindings = $review->findings
                ->whereNotIn('status', ['resolved', 'accepted', 'closed']);
            foreach ($openFindings as $finding) {
                $findings[] = $this->finding(
                    'review.closed_with_open_findings',
                    'error',
                    "review readiness: closed review [{$review->title}] still has unresolved finding [{$finding->title}]",
                    'review_finding',
                    $finding->id,
                );
            }
        }

        $targetKeys = $review->targets
            ->map(fn ($target) => $target->reviewable_type.':'.$target->reviewable_id)
            ->all();

        foreach ($review->findings as $finding) {
            array_push($findings, ...$this->checkFinding($finding, $targetKeys));
        }

        return $findings;
    }

    /**
     * @param  list<string>  $targetKeys
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    private function checkFinding(ReviewFinding $finding, array $targetKeys): array
    {
        $findings = [];

        if ($finding->status === 'open' && $finding->due_at && $finding->due_at->lt(now()->startOfDay())) {
            $findings[] = $this->finding(
                'review.finding.overdue',
                'warning',
                "review readiness: finding [{$finding->title}] was due {$finding->due_at->toDateString()} and is still open",
                'review_finding',
                $finding->id,
            );
        }

        if ($finding->reviewable_type && $finding->reviewable_id) {
            $key = $finding->reviewable_type.':'.$finding->reviewable_id;
            if (! in_array($key, $targetKeys, true)) {
                $findings[] = $this->finding(
                    'review.finding.target_unlinked',
                    'warning',
                    "review readiness: finding [{$finding->title}] points to an artifact not listed as a review target",
                    'review_finding',
                    $finding->id,
                );
            }
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
