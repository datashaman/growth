<?php

use App\Models\User;
use App\Support\ViewLens;
use Livewire\Livewire;

it('defaults to the All lens when the user has none set', function () {
    $user = User::factory()->create();

    expect($user->view_lens)->toBeNull()
        ->and($user->lens())->toBe(ViewLens::All);
});

it('persists the chosen lens on the user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('lens-switcher')
        ->set('selectedLens', ViewLens::SpecWriter->value)
        ->assertRedirect();

    expect($user->fresh()->lens())->toBe(ViewLens::SpecWriter);
});

it('ignores an unknown lens value', function () {
    $user = User::factory()->create();
    $user->switchLens(ViewLens::Reviewer);
    $this->actingAs($user);

    Livewire::test('lens-switcher')
        ->set('selectedLens', 'not-a-lens');

    expect($user->fresh()->lens())->toBe(ViewLens::Reviewer);
});

it('shows only the spec-writer sections in the sidebar', function () {
    $user = User::factory()->create();
    $user->switchLens(ViewLens::SpecWriter);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('intent'))
        ->assertSee(route('requirements'))
        ->assertSee(route('architecture'))
        ->assertDontSee(route('plan'))
        ->assertDontSee(route('verification'))
        ->assertDontSee(route('evidence'))
        ->assertDontSee(route('changes'));
});

it('shows every section in the sidebar under the All lens', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('intent'))
        ->assertSee(route('plan'))
        ->assertSee(route('verification'))
        ->assertSee(route('evidence'))
        ->assertSee(route('changes'));
});

it('always shows the Workspace group regardless of lens', function () {
    $user = User::factory()->create();
    $user->switchLens(ViewLens::SpecWriter);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('tool-invocations'))
        ->assertSee(route('feedback'));
});

it('still serves a section the active lens hides', function () {
    $user = User::factory()->create();
    $user->switchLens(ViewLens::SpecWriter);

    $this->actingAs($user)
        ->get('/plan')
        ->assertOk();
});
