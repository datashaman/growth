<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\RecordUnattributedEvent;
use App\Models\Project;
use App\Models\UnattributedGithubEvent;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Growth',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/growth',
    ]);
});

it('records a GitHub event that could not be attributed', function () {
    PlanningServer::tool(RecordUnattributedEvent::class, [
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'branch' => 'feature/lander',
        'commit_sha' => 'abc123',
        'reason' => 'missing_link',
        'url' => 'https://github.com/datashaman/growth/runs/9',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('github_repo', 'datashaman/growth')
                ->where('commit_sha', 'abc123')
                ->where('recorded', true)
                ->etc();
        });

    $event = UnattributedGithubEvent::sole();
    expect($event->branch)->toBe('feature/lander')
        ->and($event->reason)->toBe('missing_link')
        ->and($event->received_at)->not->toBeNull();
});

it('overwrites the prior row for the same repo and commit', function () {
    $args = [
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'branch' => 'feature/lander',
        'commit_sha' => 'abc123',
        'reason' => 'missing_link',
    ];

    PlanningServer::tool(RecordUnattributedEvent::class, $args)->assertOk();
    PlanningServer::tool(RecordUnattributedEvent::class, [...$args, 'reason' => 'ambiguous_branch'])->assertOk();

    expect(UnattributedGithubEvent::where('commit_sha', 'abc123')->count())->toBe(1);
    expect(UnattributedGithubEvent::sole()->reason)->toBe('ambiguous_branch');
});

it('prunes events older than the retention window', function () {
    UnattributedGithubEvent::create([
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'branch' => 'feature/stale',
        'commit_sha' => 'stale-sha',
        'reason' => 'missing_link',
        'received_at' => now()->subDays(40),
    ]);

    PlanningServer::tool(RecordUnattributedEvent::class, [
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'branch' => 'feature/fresh',
        'commit_sha' => 'fresh-sha',
        'reason' => 'missing_link',
    ])->assertOk();

    expect(UnattributedGithubEvent::where('commit_sha', 'stale-sha')->exists())->toBeFalse();
    expect(UnattributedGithubEvent::where('commit_sha', 'fresh-sha')->exists())->toBeTrue();
});

it('rejects an unknown reason', function () {
    PlanningServer::tool(RecordUnattributedEvent::class, [
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'commit_sha' => 'abc123',
        'reason' => 'made-up',
    ])->assertHasErrors();
});

it('rejects a malformed repo argument', function () {
    PlanningServer::tool(RecordUnattributedEvent::class, [
        'github_repo' => 'not-a-repo',
        'event_type' => 'check_run',
        'commit_sha' => 'abc123',
        'reason' => 'missing_link',
    ])->assertHasErrors();
});

it('rejects a repo the caller has no project for', function () {
    PlanningServer::tool(RecordUnattributedEvent::class, [
        'github_repo' => 'datashaman/foreign',
        'event_type' => 'check_run',
        'commit_sha' => 'abc123',
        'reason' => 'missing_link',
    ])->assertHasErrors();

    expect(UnattributedGithubEvent::count())->toBe(0);
});
