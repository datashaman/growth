<?php

use App\Events\WorkspaceDataChanged;
use App\Models\ToolInvocation;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('saving a ToolInvocation dispatches WorkspaceDataChanged on its workspace channel', function () {
    Event::fake([WorkspaceDataChanged::class]);

    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'tool_name' => 'upsert-requirements',
        'transport' => 'stdio',
        'success' => true,
        'duration_ms' => 42,
        'started_at' => now()->subSecond(),
        'completed_at' => now(),
    ]);

    Event::assertDispatched(
        WorkspaceDataChanged::class,
        fn (WorkspaceDataChanged $e) => $e->workspaceId === (string) $this->user->active_workspace_id,
    );
});

test('tool-invocations page renders recent invocations and refreshes on broadcast', function () {
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'tool_name' => 'upsert-requirements',
        'transport' => 'stdio',
        'success' => true,
        'duration_ms' => 42,
        'started_at' => now()->subMinute(),
        'completed_at' => now()->subMinute(),
    ]);

    $component = Livewire::test('pages::tool-invocations')
        ->assertSee('upsert-requirements');

    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'tool_name' => 'list-projects',
        'transport' => 'http',
        'success' => false,
        'error_class' => 'tool_error',
        'error_message' => 'Boom',
        'duration_ms' => 1234,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $component
        ->call('onWorkspaceDataChanged')
        ->assertSee('list-projects')
        ->assertSee('Boom');
});

test('tool-invocations page shows the acting surface and adopted role', function () {
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'acting_surface' => 'planning',
        'acting_role_name' => 'Engineering Lead',
        'tool_name' => 'upsert-plan',
        'transport' => 'http',
        'success' => true,
        'duration_ms' => 7,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    Livewire::test('pages::tool-invocations')
        ->assertSee('planning')
        ->assertSee('Engineering Lead');
});

test('listener is empty when user has no active workspace', function () {
    $orphan = User::factory()->create();
    $orphan->forceFill(['active_workspace_id' => null])->save();
    $this->actingAs($orphan);

    $listeners = Livewire::test('pages::tool-invocations')->instance()->getListeners();

    expect($listeners)->toBe([]);
});

test('tool-invocations page only shows the active workspace invocations', function () {
    $other = User::factory()->create();
    ToolInvocation::create([
        'workspace_id' => $other->active_workspace_id,
        'user_id' => $other->id,
        'tool_name' => 'foreign-tool',
        'transport' => 'stdio',
        'success' => true,
        'duration_ms' => 1,
        'started_at' => now(),
        'completed_at' => now(),
    ]);
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'tool_name' => 'local-tool',
        'transport' => 'stdio',
        'success' => true,
        'duration_ms' => 1,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    Livewire::test('pages::tool-invocations')
        ->assertSee('local-tool')
        ->assertDontSee('foreign-tool');
});
