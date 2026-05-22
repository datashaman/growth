<?php

namespace App\Growth\Digest;

use App\Growth\Lint\BaselineLinter;
use App\Growth\Lint\ChangeLinter;
use App\Growth\Lint\DesignLinter;
use App\Growth\Lint\PmpLinter;
use App\Growth\Lint\RequirementLinter;
use App\Growth\Lint\ReviewLinter;
use App\Growth\Lint\TestLinter;
use App\Models\ChangeRequest;
use App\Models\DecisionRequest;
use App\Models\Project;
use App\Models\Review;
use App\Models\ReviewFinding;
use App\Models\ReviewParticipant;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Assembles the "what is open and waiting on me" digest for a project.
 *
 * Every kind is routed through the caller's role bindings within the project;
 * nothing routed to a role the caller does not hold is included. Lint findings
 * carry no owner, so ownership is derived from the finding's subject — findings
 * whose subject has no owning role (no role column, or a null one) land in
 * `unowned`.
 *
 * The single source of truth shared by the what-needs-me MCP tool and the
 * dashboard "My Queue" panel.
 */
class WhatNeedsMeDigest
{
    /**
     * Lint subject types that carry a role column, mapped to that column.
     *
     * @var array<string, string>
     */
    private const SUBJECT_ROLE_COLUMN = [
        'change_request' => 'requester_role_id',
        'review' => 'owner_role_id',
        'review_finding' => 'owner_role_id',
        'risk' => 'owner_role_id',
        'work_item' => 'responsible_role_id',
    ];

    /**
     * Eloquent model for each role-attributable lint subject type.
     *
     * @var array<string, class-string<Model>>
     */
    private const SUBJECT_MODEL = [
        'change_request' => ChangeRequest::class,
        'review' => Review::class,
        'review_finding' => ReviewFinding::class,
        'risk' => Risk::class,
        'work_item' => WorkItem::class,
    ];

    public function __construct(
        private readonly BaselineLinter $baselineLinter,
        private readonly ChangeLinter $changeLinter,
        private readonly RequirementLinter $requirementLinter,
        private readonly DesignLinter $designLinter,
        private readonly TestLinter $testLinter,
        private readonly PmpLinter $planLinter,
        private readonly ReviewLinter $reviewLinter,
    ) {}

    /**
     * Build the digest for a caller within a project.
     *
     * @return array{
     *     change_requests: list<array<string, mixed>>,
     *     reviews: list<array<string, mixed>>,
     *     blocked_work_items: list<array<string, mixed>>,
     *     decision_requests: list<array<string, mixed>>,
     *     lint_findings: list<array<string, mixed>>,
     *     unowned_lint_findings: list<array<string, mixed>>,
     *     total: int,
     * }
     */
    public function for(Project $project, ?User $user): array
    {
        $roleIds = $user
            ? $user->roles()->where('roles.project_id', $project->id)->pluck('roles.id')->all()
            : [];

        $changeRequests = $this->changeRequests($project, $roleIds);
        $reviews = $this->reviews($project, $roleIds);
        $blockedWorkItems = $this->blockedWorkItems($project, $roleIds);
        $decisionRequests = $this->decisionRequests($roleIds);
        [$lintFindings, $unownedLintFindings] = $this->lintFindings($project, $roleIds);

        return [
            'change_requests' => $changeRequests,
            'reviews' => $reviews,
            'blocked_work_items' => $blockedWorkItems,
            'decision_requests' => $decisionRequests,
            'lint_findings' => $lintFindings,
            'unowned_lint_findings' => $unownedLintFindings,
            'total' => count($changeRequests)
                + count($reviews)
                + count($blockedWorkItems)
                + count($decisionRequests)
                + count($lintFindings),
        ];
    }

    /**
     * Approved-but-unimplemented change requests requested by the caller's roles.
     *
     * @param  list<string>  $roleIds
     * @return list<array<string, mixed>>
     */
    private function changeRequests(Project $project, array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        return $project->changeRequests()
            ->where('status', 'approved')
            ->whereIn('requester_role_id', $roleIds)
            ->orderByDesc('priority')
            ->get()
            ->map(fn (ChangeRequest $change): array => [
                'id' => $change->id,
                'reference' => $change->reference(),
                'title' => $change->title,
                'priority' => $change->priority,
            ])
            ->all();
    }

    /**
     * Open reviews awaiting sign-off from the caller's roles.
     *
     * @param  list<string>  $roleIds
     * @return list<array<string, mixed>>
     */
    private function reviews(Project $project, array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        return ReviewParticipant::query()
            ->whereIn('role_id', $roleIds)
            ->whereNull('signed_off_at')
            ->where('attendance_status', '!=', 'excused')
            ->whereHas('review', fn ($query) => $query
                ->where('project_id', $project->id)
                ->whereIn('status', ['planned', 'in_progress']))
            ->with('review')
            ->get()
            ->map(fn (ReviewParticipant $participant): array => [
                'id' => $participant->review_id,
                'title' => $participant->review?->title,
                'status' => $participant->review?->status,
                'responsibility' => $participant->responsibility,
            ])
            ->all();
    }

    /**
     * Blocked work items the caller's roles are on the hook for, either by the
     * responsible-role column or a RACI Responsible/Accountable assignment. The
     * Accountable role owns the outcome, so a block is exactly what it needs to
     * see; Consulted/Informed are advisory and stay out of the action queue.
     *
     * @param  list<string>  $roleIds
     * @return list<array<string, mixed>>
     */
    private function blockedWorkItems(Project $project, array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        return $project->workItems()
            ->where('status', 'blocked')
            ->where(fn ($query) => $query
                ->whereIn('responsible_role_id', $roleIds)
                ->orWhereHas('raciRoles', fn ($roles) => $roles
                    ->whereIn('roles.id', $roleIds)
                    ->whereIn('raci_assignments.raci', ['r', 'a'])))
            ->orderBy('name')
            ->get()
            ->map(fn (WorkItem $workItem): array => [
                'id' => $workItem->id,
                'reference' => $workItem->reference(),
                'name' => $workItem->name,
                'kind' => $workItem->kind,
            ])
            ->all();
    }

    /**
     * Open decision requests routed to the caller's roles.
     *
     * @param  list<string>  $roleIds
     * @return list<array<string, mixed>>
     */
    private function decisionRequests(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        return DecisionRequest::query()
            ->where('status', 'open')
            ->whereIn('target_role_id', $roleIds)
            ->with('targetRole')
            ->orderBy('created_at')
            ->get()
            ->map(fn (DecisionRequest $decisionRequest): array => [
                'id' => $decisionRequest->id,
                'question' => $decisionRequest->question,
                'target_role' => $decisionRequest->targetRole?->name,
                'deadline' => $decisionRequest->deadline?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Lint errors split into those routed to the caller's roles and those that
     * land on nobody ("unowned"). A finding is unowned when its subject type
     * carries no role column, or when it does but the subject's role column is
     * null. Findings attributable to a role the caller does not hold are
     * dropped entirely.
     *
     * @param  list<string>  $roleIds
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function lintFindings(Project $project, array $roleIds): array
    {
        $errors = array_filter(
            $this->allFindings($project),
            fn (array $finding): bool => $finding['severity'] === 'error',
        );

        $owners = $this->resolveFindingOwners($errors);

        $mine = [];
        $unowned = [];

        foreach ($errors as $finding) {
            $type = $finding['subject_type'];

            $ownerRoleId = isset(self::SUBJECT_ROLE_COLUMN[$type])
                ? ($owners[$type][$finding['subject_id']] ?? null)
                : null;

            if ($ownerRoleId === null) {
                $unowned[] = $finding;

                continue;
            }

            if (in_array($ownerRoleId, $roleIds, true)) {
                $mine[] = $finding;
            }
        }

        return [array_values($mine), array_values($unowned)];
    }

    /**
     * Run every linter and return the merged finding list.
     *
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    private function allFindings(Project $project): array
    {
        $findings = [
            ...$this->baselineLinter->check($project),
            ...$this->changeLinter->check($project),
            ...$this->designLinter->check($project),
            ...$this->testLinter->check($project),
            ...$this->planLinter->check($project),
            ...$this->reviewLinter->check($project),
        ];

        foreach ($project->requirements as $requirement) {
            foreach ($this->requirementLinter->check($requirement) as $finding) {
                $findings[] = $finding + [
                    'subject_type' => 'requirement',
                    'subject_id' => $requirement->id,
                ];
            }
        }

        return $findings;
    }

    /**
     * Batch-resolve the owning role id for every role-attributable subject
     * referenced by the given findings, keyed by subject type then subject id.
     *
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<string, array<string, ?string>>
     */
    private function resolveFindingOwners(array $findings): array
    {
        $idsByType = [];

        foreach ($findings as $finding) {
            $type = $finding['subject_type'];

            if (isset(self::SUBJECT_ROLE_COLUMN[$type])) {
                $idsByType[$type][$finding['subject_id']] = true;
            }
        }

        $owners = [];

        foreach ($idsByType as $type => $ids) {
            $modelClass = self::SUBJECT_MODEL[$type];
            $column = self::SUBJECT_ROLE_COLUMN[$type];

            $owners[$type] = $modelClass::query()
                ->whereKey(array_keys($ids))
                ->pluck($column, 'id')
                ->all();
        }

        return $owners;
    }
}
