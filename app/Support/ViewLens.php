<?php

namespace App\Support;

/**
 * A self-selected "view lens" the user picks to focus the webapp on the
 * surfaces relevant to how they work. The lens hides sidebar navigation
 * groups; it never blocks routes — deep links to a hidden section still work.
 *
 * Section keys match the route names of the Project sidebar group.
 */
enum ViewLens: string
{
    case All = 'all';
    case SpecWriter = 'spec_writer';
    case SpecImplementer = 'spec_implementer';
    case Reviewer = 'reviewer';

    /**
     * The Project sidebar sections this lens reveals, by route name.
     *
     * @return list<string>
     */
    public function sections(): array
    {
        return match ($this) {
            self::All => ['dashboard', 'intent', 'requirements', 'architecture', 'verification', 'plan', 'evidence', 'changes'],
            self::SpecWriter => ['dashboard', 'intent', 'requirements', 'architecture'],
            self::SpecImplementer => ['dashboard', 'plan', 'verification', 'evidence', 'changes'],
            self::Reviewer => ['dashboard', 'verification', 'changes'],
        };
    }

    /**
     * Whether this lens reveals the given Project sidebar section.
     */
    public function reveals(string $section): bool
    {
        return in_array($section, $this->sections(), true);
    }

    /**
     * The dashboard panels this lens renders, by panel key. Panel keys are:
     * counts, readiness, implementation, risks, anomalies, reviews. The
     * project header is unconditional and not listed here.
     *
     * @return list<string>
     */
    public function panels(): array
    {
        return match ($this) {
            self::All => ['counts', 'readiness', 'implementation', 'risks', 'anomalies', 'reviews'],
            self::SpecWriter => ['counts', 'readiness'],
            self::SpecImplementer => ['implementation', 'risks', 'anomalies'],
            self::Reviewer => ['readiness', 'reviews'],
        };
    }

    /**
     * Whether this lens renders the given dashboard panel.
     */
    public function revealsPanel(string $panel): bool
    {
        return in_array($panel, $this->panels(), true);
    }

    /**
     * Human-readable label for the lens switcher.
     */
    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::SpecWriter => 'Spec writer',
            self::SpecImplementer => 'Spec implementer',
            self::Reviewer => 'Reviewer / governance',
        };
    }
}
