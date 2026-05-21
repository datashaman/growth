<?php

namespace App\Support;

enum Capability: string
{
    case ManageIntent = 'manage_intent';
    case ManageRequirements = 'manage_requirements';
    case ManageArchitecture = 'manage_architecture';
    case ManagePlan = 'manage_plan';
    case ManageVerification = 'manage_verification';
    case ManageChanges = 'manage_changes';
    case ViewEvidence = 'view_evidence';
    case ViewDashboard = 'view_dashboard';

    /**
     * @return list<string>
     */
    public function sections(): array
    {
        return match ($this) {
            self::ManageIntent => ['intent'],
            self::ManageRequirements => ['requirements'],
            self::ManageArchitecture => ['architecture'],
            self::ManagePlan => ['plan'],
            self::ManageVerification => ['verification'],
            self::ManageChanges => ['changes', 'reviews'],
            self::ViewEvidence => ['evidence'],
            self::ViewDashboard => ['dashboard'],
        };
    }

    /**
     * @return list<string>
     */
    public function panels(): array
    {
        return match ($this) {
            self::ManageIntent, self::ViewDashboard => ['counts'],
            self::ManageRequirements, self::ManageArchitecture, self::ViewEvidence => ['readiness'],
            self::ManagePlan => ['implementation', 'risks'],
            self::ManageVerification => ['anomalies'],
            self::ManageChanges => ['reviews'],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ManageIntent => 'Manage intent',
            self::ManageRequirements => 'Manage requirements',
            self::ManageArchitecture => 'Manage architecture',
            self::ManagePlan => 'Manage plan',
            self::ManageVerification => 'Manage verification',
            self::ManageChanges => 'Manage changes',
            self::ViewEvidence => 'View evidence',
            self::ViewDashboard => 'View dashboard',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $capability): string => $capability->value,
            self::cases(),
        );
    }
}
