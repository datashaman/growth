<?php

use App\Models\Citation;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\ReviewFinding;
use App\Models\ReviewPlan;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    $this->aliceProject = Project::create([
        'user_id' => $this->alice->id,
        'name' => 'Apollo',
        'rigor_level' => 2,
    ]);
    $this->bobProject = Project::create([
        'user_id' => $this->bob->id,
        'name' => 'Bob',
        'rigor_level' => 2,
    ]);
});

it('registers review morph aliases', function () {
    $map = Relation::morphMap();

    expect($map)->toHaveKey('review_plan');
    expect($map)->toHaveKey('review');
    expect($map)->toHaveKey('review_finding');
    expect($map['review_plan'])->toBe(ReviewPlan::class);
    expect($map['review'])->toBe(Review::class);
    expect($map['review_finding'])->toBe(ReviewFinding::class);
});

it('relates review plans to projects, reviews, and citations', function () {
    $source = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Inspection procedure',
    ]);
    $plan = ReviewPlan::create([
        'project_id' => $this->aliceProject->id,
        'type' => 'inspection',
        'name' => 'Formal inspection',
        'procedure' => 'Prepare, inspect, rework, follow up.',
        'entry_criteria' => ['Artifact is baselined'],
        'exit_criteria' => ['Findings dispositioned'],
        'expected_responsibilities' => ['moderator', 'reviewer', 'recorder'],
        'checklist' => ['Check traceability'],
    ]);
    $review = Review::create([
        'project_id' => $this->aliceProject->id,
        'review_plan_id' => $plan->id,
        'type' => 'inspection',
        'title' => 'SRS inspection',
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'review_plan',
        'citable_id' => $plan->id,
    ]);

    expect($plan->project->is($this->aliceProject))->toBeTrue();
    expect($review->reviewPlan->is($plan))->toBeTrue();
    expect($plan->reviews()->count())->toBe(1);
    expect($this->aliceProject->reviewPlans()->count())->toBe(1);
    expect($plan->citations()->count())->toBe(1);
});

it('relates reviews to targets, findings, owner roles, and citations', function () {
    $role = Role::create(['project_id' => $this->aliceProject->id, 'name' => 'Moderator']);
    $requirement = Requirement::create([
        'project_id' => $this->aliceProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall retain review records.',
    ]);
    $source = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Inspection minutes',
    ]);
    $review = Review::create([
        'project_id' => $this->aliceProject->id,
        'owner_role_id' => $role->id,
        'type' => 'inspection',
        'title' => 'SRS inspection',
        'status' => 'held',
    ]);
    $review->targets()->create([
        'reviewable_type' => 'requirement',
        'reviewable_id' => $requirement->id,
        'context' => 'core behavior',
    ]);
    $finding = ReviewFinding::create([
        'project_id' => $this->aliceProject->id,
        'review_id' => $review->id,
        'owner_role_id' => $role->id,
        'reviewable_type' => 'requirement',
        'reviewable_id' => $requirement->id,
        'title' => 'Acceptance criteria missing',
        'severity' => 'high',
        'status' => 'open',
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'review',
        'citable_id' => $review->id,
        'locator' => 'p. 1',
    ]);

    expect($review->project->is($this->aliceProject))->toBeTrue();
    expect($review->ownerRole->is($role))->toBeTrue();
    expect($role->reviews()->count())->toBe(1);
    expect($this->aliceProject->reviews()->count())->toBe(1);
    expect($review->targets()->count())->toBe(1);
    expect($review->participants()->count())->toBe(0);
    expect($requirement->reviewTargets()->count())->toBe(1);
    expect($review->findings()->count())->toBe(1);
    expect($finding->reviewable->is($requirement))->toBeTrue();
    expect($review->citations()->count())->toBe(1);
});

it('relates review participants to reviews and roles', function () {
    $role = Role::create(['project_id' => $this->aliceProject->id, 'name' => 'Moderator']);
    $review = Review::create([
        'project_id' => $this->aliceProject->id,
        'type' => 'inspection',
        'title' => 'SRS inspection',
    ]);

    $participant = $review->participants()->create([
        'role_id' => $role->id,
        'responsibility' => 'moderator',
        'attendance_status' => 'attended',
        'signed_off_at' => now(),
    ]);

    expect($participant->review->is($review))->toBeTrue();
    expect($participant->role->is($role))->toBeTrue();
    expect($review->participants()->count())->toBe(1);
    expect($role->reviewParticipants()->count())->toBe(1);
});

it('scopes reviews and findings to the authenticated project owner', function () {
    Review::create([
        'project_id' => $this->aliceProject->id,
        'type' => 'technical_review',
        'title' => 'Alice review',
    ]);
    $bobReview = Review::create([
        'project_id' => $this->bobProject->id,
        'type' => 'technical_review',
        'title' => 'Bob review',
    ]);
    ReviewFinding::create([
        'project_id' => $this->bobProject->id,
        'review_id' => $bobReview->id,
        'title' => 'Bob finding',
    ]);

    auth()->login($this->alice);

    expect(Review::count())->toBe(1);
    expect(Review::first()->title)->toBe('Alice review');
    expect(ReviewFinding::count())->toBe(0);
});
