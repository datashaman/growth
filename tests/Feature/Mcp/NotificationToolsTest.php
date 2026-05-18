<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Common\ListUsers;
use App\Mcp\Tools\Common\SendNotification;
use App\Models\User;
use App\Models\WorkspaceMembership;
use App\Notifications\DirectMessage;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->actor = User::factory()->create();
    Passport::actingAs($this->actor, ['mcp:use']);
    $this->workspaceId = $this->actor->active_workspace_id;

    $this->member = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $this->workspaceId,
        'user_id' => $this->member->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
    ]);
});

it('sends a notification to a workspace member', function () {
    Notification::fake();

    ManagementServer::tool(SendNotification::class, [
        'user_id' => $this->member->id,
        'message' => 'The checkout mockup is ready for review.',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('user_id', $this->member->id)
                ->where('sent', true)
                ->etc();
        });

    Notification::assertSentTo(
        $this->member,
        DirectMessage::class,
        fn (DirectMessage $notification) => $notification->body() === 'The checkout mockup is ready for review.',
    );
});

it('does not send a notification to a user outside the workspace', function () {
    Notification::fake();

    $outsider = User::factory()->create();

    ManagementServer::tool(SendNotification::class, [
        'user_id' => $outsider->id,
        'message' => 'You should not see this.',
    ])->assertHasErrors();

    Notification::assertNothingSent();
});

it('lists the members of the active workspace', function () {
    ManagementServer::tool(ListUsers::class, [])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('workspace_id', $this->workspaceId)
                ->where('total', 2)
                ->where('results', function ($results) {
                    expect(collect($results)->pluck('user_id')->all())
                        ->toContain($this->actor->id, $this->member->id);

                    return true;
                })
                ->etc();
        });
});
