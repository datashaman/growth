<?php

use App\Models\Project;
use App\Models\User;
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
