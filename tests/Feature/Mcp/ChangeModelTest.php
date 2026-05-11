<?php

use App\Models\ChangeRequest;
use App\Models\Citation;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Review;
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
        'integrity_level' => 2,
    ]);
    $this->bobProject = Project::create([
        'user_id' => $this->bob->id,
        'name' => 'Bob',
        'integrity_level' => 2,
    ]);
});

it('registers the change request morph alias', function () {
    $map = Relation::morphMap();

    expect($map)->toHaveKey('change_request');
    expect($map['change_request'])->toBe(ChangeRequest::class);
});

it('relates change requests to projects, requester roles, reviews, impacts, and citations', function () {
    $role = Role::create(['project_id' => $this->aliceProject->id, 'name' => 'Product Owner']);
    $review = Review::create([
        'project_id' => $this->aliceProject->id,
        'type' => 'technical_review',
        'title' => 'Change review',
    ]);
    $requirement = Requirement::create([
        'project_id' => $this->aliceProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall retain change records.',
    ]);
    $source = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Change board notes',
    ]);
    $change = ChangeRequest::create([
        'project_id' => $this->aliceProject->id,
        'requester_role_id' => $role->id,
        'review_id' => $review->id,
        'title' => 'Add change register',
        'category' => 'requirements',
        'status' => 'proposed',
        'priority' => 'high',
    ]);
    $impact = $change->impacts()->create([
        'impactable_type' => 'requirement',
        'impactable_id' => $requirement->id,
        'impact_kind' => 'modifies',
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'change_request',
        'citable_id' => $change->id,
    ]);

    expect($change->project->is($this->aliceProject))->toBeTrue();
    expect($change->requesterRole->is($role))->toBeTrue();
    expect($change->review->is($review))->toBeTrue();
    expect($role->requestedChanges()->count())->toBe(1);
    expect($review->changeRequests()->count())->toBe(1);
    expect($change->impacts()->count())->toBe(1);
    expect($impact->impactable->is($requirement))->toBeTrue();
    expect($requirement->changeImpacts()->count())->toBe(1);
    expect($change->citations()->count())->toBe(1);
});

it('scopes change requests to the authenticated project owner', function () {
    ChangeRequest::create([
        'project_id' => $this->aliceProject->id,
        'title' => 'Alice change',
        'category' => 'requirements',
    ]);
    ChangeRequest::create([
        'project_id' => $this->bobProject->id,
        'title' => 'Bob change',
        'category' => 'requirements',
    ]);

    auth()->login($this->alice);

    expect(ChangeRequest::count())->toBe(1);
    expect(ChangeRequest::first()->title)->toBe('Alice change');
});
