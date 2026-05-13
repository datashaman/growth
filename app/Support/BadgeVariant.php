<?php

namespace App\Support;

/**
 * Central source of badge colour mappings for the webapp.
 *
 * Semantic vocab (Flux free recognises literal Tailwind names only):
 *   green  → terminal success
 *   teal   → intermediate success
 *   blue   → in-flight work
 *   sky    → not yet started / low
 *   amber  → warning / attention
 *   red    → blocked / failing / high
 *   zinc   → neutral / inert
 *   purple → deliverable / system level
 *   indigo → work_package / integration level
 */
class BadgeVariant
{
    public static function gate(string $status): string
    {
        return match ($status) {
            'pass' => 'green',
            'warn' => 'amber',
            'fail' => 'red',
            default => 'zinc',
        };
    }

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
            'hit' => 'green',
            'missed' => 'red',
            'deferred' => 'zinc',
            default => 'zinc',
        };
    }

    public static function priority(?string $priority): string
    {
        return match ($priority) {
            'high' => 'red',
            'medium' => 'amber',
            'low' => 'sky',
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
