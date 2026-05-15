<?php

use App\Events\ProjectDataChanged;
use App\Models\CheckRunEvidence;
use App\Models\Project;
use App\Models\Review;
use App\Models\ReviewFinding;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $this->review = Review::create([
        'project_id' => $this->project->id,
        'type' => 'inspection',
        'title' => 'SRS inspection',
        'status' => 'held',
    ]);

    $this->role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Moderator',
    ]);
});

test('saving a ReviewFinding dispatches ProjectDataChanged', function () {
    Event::fake([ProjectDataChanged::class]);

    ReviewFinding::create([
        'project_id' => $this->project->id,
        'review_id' => $this->review->id,
        'title' => 'Acceptance criteria missing',
        'severity' => 'high',
        'status' => 'open',
    ]);

    Event::assertDispatched(ProjectDataChanged::class, fn (ProjectDataChanged $e) => $e->projectId === (string) $this->project->id);
});

test('saving a ReviewParticipant dispatches via review relation', function () {
    Event::fake([ProjectDataChanged::class]);

    $this->review->participants()->create([
        'role_id' => $this->role->id,
        'responsibility' => 'moderator',
        'attendance_status' => 'attended',
    ]);

    Event::assertDispatched(ProjectDataChanged::class, fn (ProjectDataChanged $e) => $e->projectId === (string) $this->project->id);
});

test('saving a CheckRunEvidence dispatches via delivery link → work item', function () {
    Event::fake([ProjectDataChanged::class]);

    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'name' => 'Ship the lander',
        'kind' => 'task',
        'status' => 'in_progress',
    ]);
    $deliveryLink = WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'pull_request',
        'ref' => 'PR-1',
    ]);

    CheckRunEvidence::create([
        'work_item_delivery_link_id' => $deliveryLink->id,
        'name' => 'unit-tests',
        'status' => 'in_progress',
    ]);

    Event::assertDispatched(ProjectDataChanged::class, fn (ProjectDataChanged $e) => $e->projectId === (string) $this->project->id);
});

test('reviews.show refreshes when onProjectDataChanged is called', function () {
    $this->review->findings()->create([
        'project_id' => $this->project->id,
        'title' => 'Existing finding',
        'severity' => 'medium',
        'status' => 'open',
    ]);

    $component = Livewire::test('pages::reviews.show', ['review' => $this->review])
        ->assertSee('Existing finding');

    $this->review->findings()->create([
        'project_id' => $this->project->id,
        'title' => 'Newly broadcast finding',
        'severity' => 'high',
        'status' => 'open',
    ]);

    $component
        ->call('onProjectDataChanged')
        ->assertSee('Newly broadcast finding');
});

test('work-items.show refreshes when onProjectDataChanged is called', function () {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'name' => 'Ship the lander',
        'kind' => 'task',
        'status' => 'in_progress',
    ]);
    $deliveryLink = WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'pull_request',
        'ref' => 'PR-1',
    ]);
    CheckRunEvidence::create([
        'work_item_delivery_link_id' => $deliveryLink->id,
        'name' => 'pre-existing-check',
        'status' => 'queued',
    ]);

    $component = Livewire::test('pages::work-items.show', ['workItem' => $workItem])
        ->assertSee('pre-existing-check');

    CheckRunEvidence::create([
        'work_item_delivery_link_id' => $deliveryLink->id,
        'name' => 'newly-broadcast-check',
        'status' => 'in_progress',
    ]);

    $component
        ->call('onProjectDataChanged')
        ->assertSee('newly-broadcast-check');
});

test('listener key on detail pages reflects the loaded model project', function () {
    $reviewListeners = Livewire::test('pages::reviews.show', ['review' => $this->review])
        ->instance()->getListeners();

    expect($reviewListeners)->toHaveKey('echo-private:projects.'.$this->project->id.',ProjectDataChanged');
});
