<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Tools\Changes\ApproveChangeRequest;
use App\Mcp\Tools\Changes\SubmitChangeRequest;
use App\Mcp\Tools\Changes\SubscribeChangeRequest;
use App\Mcp\Tools\Changes\UnsubscribeChangeRequest;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WorkspaceMembership;
use App\Notifications\ChangeRequestDecided;
use App\Notifications\ChangeRequestStatusChanged;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Subscriptions',
        'rigor_level' => 2,
    ]);

    $this->makeChange = fn (string $status = 'proposed'): ChangeRequest => ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Change',
        'category' => 'scope',
        'status' => $status,
    ]);
});

it('subscribes the calling user to a change request', function () {
    $change = ($this->makeChange)();

    GovernanceServer::tool(SubscribeChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('subscribed', true)
            ->where('already_subscribed', false)
            ->etc());

    $subscription = Subscription::query()->sole();

    expect($subscription->user_id)->toBe($this->user->id)
        ->and($subscription->subscribable_id)->toBe($change->id)
        ->and($subscription->subscribable_type)->toBe('change_request');
});

it('is idempotent when subscribing twice', function () {
    $change = ($this->makeChange)();

    GovernanceServer::tool(SubscribeChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk();

    GovernanceServer::tool(SubscribeChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('already_subscribed', true)->etc());

    expect(Subscription::query()->count())->toBe(1);
});

it('unsubscribes the calling user from a change request', function () {
    $change = ($this->makeChange)();
    Subscription::factory()->for($this->user)->for($change, 'subscribable')->create();

    GovernanceServer::tool(UnsubscribeChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('subscribed', false)
            ->where('was_subscribed', true)
            ->etc());

    expect(Subscription::query()->count())->toBe(0);
});

it('is a clean no-op when unsubscribing without a subscription', function () {
    $change = ($this->makeChange)();

    GovernanceServer::tool(UnsubscribeChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('was_subscribed', false)->etc());
});

it('rejects subscribing to an unknown change request', function () {
    GovernanceServer::tool(SubscribeChangeRequest::class, ['change_request_id' => 'missing'])
        ->assertHasErrors();
});

it('does not subscribe to a change request in another workspace', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Elsewhere',
        'rigor_level' => 2,
    ]);
    $change = ChangeRequest::create([
        'project_id' => $otherProject->id,
        'title' => 'Change',
        'category' => 'scope',
        'status' => 'proposed',
    ]);

    GovernanceServer::tool(SubscribeChangeRequest::class, ['change_request_id' => $change->id])
        ->assertHasErrors();

    expect(Subscription::query()->count())->toBe(0);
});

it('notifies subscribers when a change request transitions status', function () {
    $subscriber = User::factory()->create();
    $change = ($this->makeChange)();
    Subscription::factory()->for($subscriber)->for($change, 'subscribable')->create();

    Notification::fake();

    GovernanceServer::tool(SubmitChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk();

    Notification::assertSentTo($subscriber, ChangeRequestStatusChanged::class, function ($notification) use ($subscriber) {
        $payload = $notification->toArray($subscriber);

        return $payload['title'] === 'CR-001 status changed'
            && str_contains($payload['body'], 'proposed')
            && str_contains($payload['body'], 'under review')
            && $payload['url'] !== null;
    });
});

it('sends a subscriber both the decision and the status-change notification', function () {
    $subscriber = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $subscriber->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
    ]);
    $change = ($this->makeChange)('under_review');
    Subscription::factory()->for($subscriber)->for($change, 'subscribable')->create();

    Notification::fake();

    GovernanceServer::tool(ApproveChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk();

    // Distinct events: ChangeRequestDecided is the workspace-wide CCB
    // announcement; ChangeRequestStatusChanged is the subscription ping.
    Notification::assertSentTo($subscriber, ChangeRequestStatusChanged::class);
    Notification::assertSentTo($subscriber, ChangeRequestDecided::class);
});

it('does not notify the actor who made the change', function () {
    $change = ($this->makeChange)();
    Subscription::factory()->for($this->user)->for($change, 'subscribable')->create();

    Notification::fake();

    GovernanceServer::tool(SubmitChangeRequest::class, ['change_request_id' => $change->id])
        ->assertOk();

    Notification::assertNotSentTo($this->user, ChangeRequestStatusChanged::class);
});
