<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceMembership;

it('lets a member view and update a project in their workspace', function () {
    $owner = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $owner->active_workspace_id,
        'name' => 'Owned',
        'rigor_level' => 2,
    ]);

    $member = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $member->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
    ]);

    expect($member->can('view', $project))->toBeTrue()
        ->and($member->can('update', $project))->toBeTrue()
        ->and($member->can('delete', $project))->toBeFalse();
});

it('denies updates from a viewer in the workspace', function () {
    $owner = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $owner->active_workspace_id,
        'name' => 'Owned',
        'rigor_level' => 2,
    ]);

    $viewer = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $viewer->id,
        'role' => WorkspaceMembership::ROLE_VIEWER,
    ]);

    expect($viewer->can('view', $project))->toBeTrue()
        ->and($viewer->can('update', $project))->toBeFalse()
        ->and($viewer->can('delete', $project))->toBeFalse();
});

it('denies all access from a user outside the workspace', function () {
    $owner = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $owner->active_workspace_id,
        'name' => 'Owned',
        'rigor_level' => 2,
    ]);

    $stranger = User::factory()->create();

    expect($stranger->can('view', $project))->toBeFalse()
        ->and($stranger->can('update', $project))->toBeFalse()
        ->and($stranger->can('delete', $project))->toBeFalse();
});

it('allows admins to delete a project', function () {
    $owner = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $owner->active_workspace_id,
        'name' => 'Owned',
        'rigor_level' => 2,
    ]);

    $admin = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $admin->id,
        'role' => WorkspaceMembership::ROLE_ADMIN,
    ]);

    expect($admin->can('delete', $project))->toBeTrue();
});
