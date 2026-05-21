<?php

declare(strict_types=1);

use App\Support\Capability;
use App\Support\Lens;

it('defines the starter capability set', function (): void {
    expect(Capability::values())->toEqualCanonicalizing([
        'manage_intent',
        'manage_requirements',
        'manage_architecture',
        'manage_plan',
        'manage_verification',
        'manage_changes',
        'view_evidence',
        'view_dashboard',
    ]);
});

it('maps every capability to at least one section and panel', function (Capability $capability): void {
    expect($capability->sections())->not->toBeEmpty()
        ->and($capability->panels())->not->toBeEmpty();
})->with(Capability::cases());

it('builds a derived lens from capability sections and panels', function (): void {
    $lens = Lens::fromCapabilities([
        Capability::ManagePlan,
        Capability::ManageVerification,
        Capability::ViewEvidence,
    ]);

    expect($lens->reveals('plan'))->toBeTrue()
        ->and($lens->reveals('verification'))->toBeTrue()
        ->and($lens->reveals('evidence'))->toBeTrue()
        ->and($lens->reveals('intent'))->toBeFalse()
        ->and($lens->revealsPanel('implementation'))->toBeTrue()
        ->and($lens->revealsPanel('risks'))->toBeTrue()
        ->and($lens->revealsPanel('anomalies'))->toBeTrue()
        ->and($lens->revealsPanel('readiness'))->toBeTrue()
        ->and($lens->revealsPanel('reviews'))->toBeFalse();
});
