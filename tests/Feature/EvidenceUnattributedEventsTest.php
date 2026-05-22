<?php

use App\Models\Project;
use App\Models\UnattributedGithubEvent;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/growth',
    ]);
});

test('it surfaces an unattributed GitHub event for the selected project repo', function () {
    UnattributedGithubEvent::create([
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'branch' => 'feature/lander',
        'commit_sha' => 'abc1234567890',
        'reason' => 'missing_link',
        'received_at' => now(),
    ]);

    Livewire::test('pages::evidence')
        ->assertSee('could not be matched to a work item')
        ->assertSee('feature/lander')
        ->assertSee('has no Growth-Work-Item trailer');
});

test('it states the unmatched-event reason once and lists every event under it', function () {
    foreach (['aaa1234567890', 'bbb1234567890', 'ccc1234567890'] as $sha) {
        UnattributedGithubEvent::create([
            'github_repo' => 'datashaman/growth',
            'event_type' => 'check_run',
            'branch' => 'feature/lander',
            'commit_sha' => $sha,
            'reason' => 'missing_link',
            'received_at' => now(),
        ]);
    }

    $html = Livewire::test('pages::evidence')->html();

    // The shared explanation appears once, not once per event.
    expect(substr_count($html, 'has no Growth-Work-Item trailer'))->toBe(1)
        // …while every event is still listed as a subject.
        ->and($html)->toContain('aaa123456789')
        ->and($html)->toContain('bbb123456789')
        ->and($html)->toContain('ccc123456789');
});

test('it explains an ambiguous branch differently', function () {
    UnattributedGithubEvent::create([
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'branch' => 'feature/contested',
        'commit_sha' => 'def1234567890',
        'reason' => 'ambiguous_branch',
        'received_at' => now(),
    ]);

    Livewire::test('pages::evidence')
        ->assertSee('bound to more than one work item');
});

test('it hides unattributed events past the retention window', function () {
    UnattributedGithubEvent::create([
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'branch' => 'feature/ancient',
        'commit_sha' => 'old1234567890',
        'reason' => 'missing_link',
        'received_at' => now()->subDays(UnattributedGithubEvent::RETENTION_DAYS + 1),
    ]);

    Livewire::test('pages::evidence')
        ->assertDontSee('feature/ancient')
        ->assertDontSee('could not be matched to a work item');
});

test('it can dismiss unattributed event groups without deleting the audit record', function () {
    foreach (['aaa1234567890', 'bbb1234567890'] as $sha) {
        UnattributedGithubEvent::create([
            'github_repo' => 'datashaman/growth',
            'event_type' => 'check_run',
            'branch' => 'feature/lander',
            'commit_sha' => $sha,
            'reason' => 'missing_link',
            'received_at' => now(),
        ]);
    }

    Livewire::test('pages::evidence')
        ->assertSee('could not be matched to a work item')
        ->assertSee('Mark resolved')
        ->call('dismissUnattributedEventGroup', 'unbound')
        ->assertDontSee('could not be matched to a work item')
        ->assertDontSee('feature/lander');

    expect(UnattributedGithubEvent::count())->toBe(2)
        ->and(UnattributedGithubEvent::whereNull('resolved_at')->count())->toBe(0)
        ->and(UnattributedGithubEvent::where('resolved_by_user_id', $this->user->id)->count())->toBe(2)
        ->and(UnattributedGithubEvent::where('resolution_note', 'Dismissed from Evidence after operator review.')->count())->toBe(2);
});

test('it does not surface resolved unattributed events', function () {
    UnattributedGithubEvent::create([
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'branch' => 'feature/handled',
        'commit_sha' => 'handled1234567890',
        'reason' => 'missing_link',
        'received_at' => now(),
        'resolved_at' => now(),
        'resolved_by_user_id' => $this->user->id,
        'resolution_note' => 'Dismissed from Evidence after operator review.',
    ]);

    Livewire::test('pages::evidence')
        ->assertDontSee('feature/handled')
        ->assertDontSee('could not be matched to a work item');
});

test('it does not surface unattributed events for a repo in another workspace', function () {
    $other = User::factory()->create();
    Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/foreign',
    ]);
    UnattributedGithubEvent::create([
        'github_repo' => 'datashaman/foreign',
        'event_type' => 'check_run',
        'branch' => 'feature/foreign',
        'commit_sha' => 'fff1234567890',
        'reason' => 'missing_link',
        'received_at' => now(),
    ]);

    Livewire::test('pages::evidence')
        ->assertDontSee('feature/foreign');
});
