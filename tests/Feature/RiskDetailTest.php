<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Lunar Lander',
        'integrity_level' => 2,
    ]);
});

test('risk detail page renders for the owner', function () {
    $risk = $this->project->risks()->create([
        'title' => 'Heat shield delamination',
        'description' => 'Ablator may detach during peak heating.',
        'category' => 'technical',
        'probability' => 'high',
        'impact' => 'high',
        'status' => 'mitigating',
        'mitigation_plan' => 'Add thermal blanket layer.',
    ]);

    $this->actingAs($this->user)
        ->get('/risks/'.$risk->id)
        ->assertOk()
        ->assertSee('Heat shield delamination')
        ->assertSee('Ablator may detach')
        ->assertSee('Add thermal blanket')
        ->assertSee('Lunar Lander')
        ->assertSee('mitigating');
});

test('risk detail page 404s for non-owner', function () {
    $risk = $this->project->risks()->create([
        'title' => 'Heat shield delamination',
        'category' => 'technical',
        'probability' => 'high',
        'impact' => 'high',
        'status' => 'mitigating',
    ]);

    $bob = User::factory()->create();

    $this->actingAs($bob)
        ->get('/risks/'.$risk->id)
        ->assertNotFound();
});

test('risk detail page redirects guests to login', function () {
    $risk = $this->project->risks()->create([
        'title' => 'Heat shield delamination',
        'category' => 'technical',
        'probability' => 'high',
        'impact' => 'high',
        'status' => 'mitigating',
    ]);

    $this->get('/risks/'.$risk->id)->assertRedirect('/login');
});
