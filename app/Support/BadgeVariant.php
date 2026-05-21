<?php

namespace App\Support;

/**
 * Central source of badge colour mappings for the webapp.
 *
 * Semantic vocab (Flux free recognises literal Tailwind names only):
 *   green  → terminal success
 *   teal   → intermediate success
 *   blue   → in-flight work
 *   orange → high priority
 *   sky    → not yet started / medium priority
 *   amber  → warning / attention
 *   red    → blocked / failing / critical priority
 *   zinc   → neutral / inert / low priority
 *   purple → deliverable / system level
 *   indigo → work_package / integration level
 */
class BadgeVariant
{
    /**
     * A gate's pass/warn/fail status and a finding's error/warning/info severity
     * are two axes that meet on the Readiness card. They keep their own words
     * (different domain concepts) but share one colour axis so the card reads
     * coherently: gate `fail` ≙ finding `error` (red); gate `warn` ≙ finding
     * `warning` (amber); gate `pass` (green); anything else neutral (zinc).
     */
    public static function gate(string $status): string
    {
        return match ($status) {
            'pass' => 'green',
            'warn' => 'amber',
            'fail' => 'red',
            default => 'zinc',
        };
    }

    /**
     * Finding severity, colour-aligned with {@see self::gate()}: `error` ≙ gate
     * `fail` (red), `warning` ≙ gate `warn` (amber), info/other neutral (zinc).
     */
    public static function finding(string $severity): string
    {
        return match ($severity) {
            'error' => 'red',
            'warning' => 'amber',
            default => 'zinc',
        };
    }

    public static function workItemStatus(string $status): string
    {
        return match ($status) {
            'done' => 'green',
            'in_progress' => 'blue',
            'blocked' => 'red',
            'todo' => 'sky',
            'cancelled' => 'zinc',
            default => 'zinc',
        };
    }

    public static function workItemKind(string $kind): string
    {
        return match ($kind) {
            'deliverable' => 'purple',
            'work_package' => 'indigo',
            default => 'zinc',
        };
    }

    public static function implementationState(string $state): string
    {
        return match ($state) {
            'deployed' => 'green',
            'validated' => 'teal',
            'implemented' => 'blue',
            'blocked_by_checks' => 'red',
            'done_without_evidence' => 'amber',
            'planned' => 'sky',
            default => 'zinc',
        };
    }

    public static function milestoneStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'sky',
            'achieved' => 'green',
            default => 'zinc',
        };
    }

    public static function planStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'sky',
            'baselined' => 'blue',
            'active' => 'green',
            'closed' => 'zinc',
            default => 'zinc',
        };
    }

    public static function projectStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'zinc',
            'active' => 'green',
            'archived' => 'amber',
            'closed' => 'red',
            default => 'zinc',
        };
    }

    /**
     * One priority colour scale shared by every priority surface (requirements,
     * change requests, …). A given priority word therefore reads identically
     * everywhere. `red` is reserved for `critical` so it stands out; `high` steps
     * down to `orange`; `medium` is `sky` (calm/informational — deliberately not
     * the `amber` warning colour, which would read as "caution" for the default
     * middle level); `low` is muted `zinc`. Change requests add `critical` above
     * this scale; requirement priority tops out at `high`.
     */
    public static function priority(?string $priority): string
    {
        return match ($priority) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'sky',
            'low' => 'zinc',
            default => 'zinc',
        };
    }

    public static function doc(string $doc): string
    {
        return match ($doc) {
            'strs' => 'purple',
            'syrs' => 'indigo',
            'srs' => 'blue',
            default => 'zinc',
        };
    }

    public static function stakeholderKind(string $kind): string
    {
        return match ($kind) {
            'individual' => 'blue',
            'class' => 'indigo',
            default => 'zinc',
        };
    }

    public static function designElementKind(string $kind): string
    {
        return match ($kind) {
            'entity' => 'purple',
            'relationship' => 'indigo',
            'attribute' => 'blue',
            'constraint' => 'amber',
            default => 'zinc',
        };
    }

    public static function testLevel(string $level): string
    {
        return match ($level) {
            'system' => 'purple',
            'integration' => 'indigo',
            'unit' => 'blue',
            default => 'zinc',
        };
    }

    public static function anomalySeverity(string $severity): string
    {
        return match ($severity) {
            'critical', 'high' => 'red',
            'medium' => 'amber',
            'low' => 'sky',
            default => 'zinc',
        };
    }

    public static function anomalyStatus(string $status): string
    {
        return match ($status) {
            'open' => 'red',
            'investigating' => 'amber',
            'resolved' => 'green',
            'closed' => 'zinc',
            default => 'zinc',
        };
    }

    public static function testRunStatus(string $status): string
    {
        return match ($status) {
            'pass' => 'green',
            'fail' => 'red',
            'blocked' => 'amber',
            'skipped' => 'zinc',
            default => 'zinc',
        };
    }

    public static function riskStatus(string $status): string
    {
        return match ($status) {
            'identified' => 'sky',
            'assessed', 'mitigating' => 'blue',
            'mitigated' => 'green',
            'accepted' => 'amber',
            'realized' => 'red',
            'closed' => 'zinc',
            default => 'zinc',
        };
    }

    public static function riskExposure(?string $probability, ?string $impact): string
    {
        $score = self::levelScore($probability) * self::levelScore($impact);

        return match (true) {
            $score >= 6 => 'red',
            $score >= 3 => 'amber',
            $score >= 1 => 'sky',
            default => 'zinc',
        };
    }

    public static function riskExposureLabel(?string $probability, ?string $impact): string
    {
        $score = self::levelScore($probability) * self::levelScore($impact);

        if ($score === 0) {
            return '—';
        }

        return match (true) {
            $score >= 6 => 'high',
            $score >= 3 => 'medium',
            default => 'low',
        };
    }

    public static function reviewStatus(string $status): string
    {
        return match ($status) {
            'planned' => 'sky',
            'in_progress' => 'blue',
            'held' => 'green',
            'closed', 'cancelled' => 'zinc',
            default => 'zinc',
        };
    }

    public static function reviewDecision(?string $decision): string
    {
        return match ($decision) {
            'accepted' => 'green',
            'accepted_with_actions' => 'teal',
            'rework_required' => 'amber',
            'rejected' => 'red',
            'deferred' => 'zinc',
            default => 'zinc',
        };
    }

    public static function releaseStatus(string $status): string
    {
        return match ($status) {
            'released' => 'green',
            'candidate' => 'blue',
            'planned' => 'sky',
            'cancelled' => 'zinc',
            default => 'zinc',
        };
    }

    public static function deploymentStatus(string $status): string
    {
        return match ($status) {
            'succeeded' => 'green',
            'in_progress' => 'blue',
            'planned' => 'sky',
            'failed' => 'red',
            'rolled_back' => 'amber',
            'cancelled' => 'zinc',
            default => 'zinc',
        };
    }

    public static function deliveryType(string $type): string
    {
        return match ($type) {
            'pull_request' => 'indigo',
            'commit' => 'blue',
            'branch' => 'sky',
            'evidence' => 'purple',
            default => 'zinc',
        };
    }

    public static function changeRequestStatus(string $status): string
    {
        return match ($status) {
            'proposed' => 'sky',
            'under_review' => 'blue',
            'approved' => 'teal',
            'implemented' => 'green',
            'rejected' => 'red',
            'deferred' => 'amber',
            'cancelled' => 'zinc',
            default => 'zinc',
        };
    }

    public static function changeRequestDecision(?string $decision): string
    {
        return match ($decision) {
            'approved' => 'green',
            'rejected' => 'red',
            'deferred' => 'amber',
            default => 'zinc',
        };
    }

    public static function checkConclusion(?string $conclusion): string
    {
        return match ($conclusion) {
            'success' => 'green',
            'failure', 'timed_out', 'action_required' => 'red',
            'cancelled' => 'amber',
            'skipped', 'neutral' => 'zinc',
            default => 'sky',
        };
    }

    public static function feedbackCategory(string $category): string
    {
        return match ($category) {
            'bug' => 'red',
            'difficulty' => 'amber',
            'missing_capability' => 'indigo',
            'suggestion' => 'blue',
            default => 'zinc',
        };
    }

    public static function feedbackStatus(string $status): string
    {
        return match ($status) {
            'new' => 'sky',
            'triaged' => 'blue',
            'resolved' => 'green',
            default => 'zinc',
        };
    }

    public static function workspaceRole(string $role): string
    {
        return match ($role) {
            'owner' => 'purple',
            'admin' => 'indigo',
            'member' => 'blue',
            'viewer' => 'zinc',
            default => 'zinc',
        };
    }

    private static function levelScore(?string $level): int
    {
        return match ($level) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }
}
