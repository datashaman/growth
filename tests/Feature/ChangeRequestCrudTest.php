<?php

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

test('owner can view a change request show page', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Switch to oxygen-rich combustion',
        'description' => 'Replace existing burner with O2-augmented variant.',
        'rationale' => 'Improves combustion stability under load.',
        'category' => 'design',
        'status' => 'under_review',
        'priority' => 'high',
    ]);

    $this->actingAs($this->user)
        ->get('/change-requests/'.$cr->id)
        ->assertOk()
        ->assertSee('Switch to oxygen-rich combustion')
        ->assertSee('Replace existing burner with O2-augmented variant.')
        ->assertSee('Improves combustion stability under load.')
        ->assertSee('under review');
});

test('change request prose renders safe markdown on detail and plain previews on the index', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Markdown change',
        'description' => "**Replace** the burner.\n\n<script>alert('x')</script>",
        'rationale' => "- Keeps the burn stable\n- Reduces operator ambiguity",
        'decision_rationale' => 'Approved for **safety**.',
        'category' => 'design',
        'status' => 'approved',
        'priority' => 'high',
        'decision' => 'approved',
    ]);

    $this->actingAs($this->user)
        ->get('/change-requests/'.$cr->id)
        ->assertOk()
        ->assertSee('<strong>Replace</strong>', false)
        ->assertSee('<li>Keeps the burn stable</li>', false)
        ->assertSee('Approved for <strong>safety</strong>.', false)
        ->assertDontSee("alert('x')");

    Livewire::actingAs($this->user)
        ->test('pages::changes')
        ->assertSee('Replace the burner.')
        ->assertDontSee('**Replace**');
});

test('impacts render named, linked artifacts instead of type:ULID', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Rework vendor onboarding', 'category' => 'scope',
        'status' => 'under_review', 'priority' => 'high',
    ]);
    $workItem = $this->project->workItems()->create([
        'kind' => 'task', 'name' => 'Vendor dashboard shell', 'status' => 'todo',
    ]);
    $cr->impacts()->create([
        'impactable_type' => 'work_item',
        'impactable_id' => $workItem->id,
        'impact_kind' => 'modifies',
        'description' => 'Touches the shell layout.',
    ]);

    $this->actingAs($this->user)
        ->get('/change-requests/'.$cr->id)
        ->assertOk()
        ->assertSee($workItem->reference().' — Vendor dashboard shell', false)
        ->assertSee(route('work-items.show', $workItem), false)
        ->assertDontSee('work_item:'.$workItem->id);
});

test('an impact on a type without a detail page shows a name but no link', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Shift the launch gate', 'category' => 'scope',
        'status' => 'under_review', 'priority' => 'medium',
    ]);
    $milestone = $this->project->milestones()->create(['name' => 'Launch readiness']);
    $cr->impacts()->create([
        'impactable_type' => 'milestone',
        'impactable_id' => $milestone->id,
        'impact_kind' => 'modifies',
    ]);

    $this->actingAs($this->user)
        ->get('/change-requests/'.$cr->id)
        ->assertOk()
        ->assertSee('Launch readiness')
        ->assertSee('milestone')
        ->assertDontSee('milestone:'.$milestone->id);
});

test('approval events render non-wrapping metadata and badge-style transitions', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Approve the launch gate',
        'category' => 'scope',
        'status' => 'approved',
        'priority' => 'high',
        'decision' => 'approved',
    ]);
    $cr->approvalEvents()->create([
        'recorded_by_user_id' => $this->user->id,
        'from_status' => 'under_review',
        'to_status' => 'approved',
        'from_decision' => null,
        'to_decision' => 'approved',
        'rationale' => 'Risk accepted.',
        'recorded_at' => Carbon::parse('2026-05-22 13:20:00'),
    ]);

    $this->actingAs($this->user)
        ->get('/change-requests/'.$cr->id)
        ->assertOk()
        ->assertSee('2026-05-22 13:20')
        ->assertSee($this->user->name)
        ->assertSee('under review')
        ->assertSee('approved')
        ->assertSee('->', false)
        ->assertSee('whitespace-nowrap', false);
});

test('changes index links each title to the show page', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Initial', 'category' => 'scope',
        'status' => 'proposed', 'priority' => 'low',
    ]);

    $this->actingAs($this->user)
        ->get('/changes?project='.$this->project->id)
        ->assertOk()
        ->assertSee(route('change-requests.show', $cr), false);
});

test('show page 404s for a change request in another project', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $bobCr = $bobProject->changeRequests()->create([
        'title' => 'Bob CR', 'category' => 'scope',
        'status' => 'proposed', 'priority' => 'low',
    ]);

    $this->actingAs($this->user)
        ->get('/change-requests/'.$bobCr->id)
        ->assertNotFound();
});

test('changes page sidebar item is reachable', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Changes');
});

test('changes page lists change requests by number, not creation time', function () {
    $this->actingAs($this->user);

    // Numbers are assigned sequentially. Force created_at into the opposite
    // order so a created_at sort would produce the wrong sequence — the list
    // must follow the deterministic CR-NNN number instead.
    $one = $this->project->changeRequests()->create(['title' => 'One', 'category' => 'scope']);
    $two = $this->project->changeRequests()->create(['title' => 'Two', 'category' => 'scope']);
    $three = $this->project->changeRequests()->create(['title' => 'Three', 'category' => 'scope']);

    $one->forceFill(['created_at' => now()])->save();
    $two->forceFill(['created_at' => now()->subDay()])->save();
    $three->forceFill(['created_at' => now()->subDays(2)])->save();

    expect([$one->number, $two->number, $three->number])->toBe([1, 2, 3]);

    Livewire::test('pages::changes')
        ->assertSeeInOrder([
            $three->reference(),
            $two->reference(),
            $one->reference(),
        ]);
});
