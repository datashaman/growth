<?php

/*
 * #374: the tool-invocations feed can be filtered (by result, transport, tool)
 * so failures are easy to isolate, and the notification bell links to the full
 * Notifications page.
 */

use App\Models\ToolInvocation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->workspaceId = $this->user->active_workspace_id;
});

function makeToolInvocation(string $workspaceId, string $tool, bool $success, string $transport = 'stdio'): ToolInvocation
{
    return ToolInvocation::create([
        'workspace_id' => $workspaceId,
        'user_id' => null,
        'tool_name' => $tool,
        'transport' => $transport,
        'success' => $success,
        'duration_ms' => 10,
        'started_at' => now()->subSecond(),
        'completed_at' => now(),
    ]);
}

test('the result filter isolates errors', function () {
    makeToolInvocation($this->workspaceId, 'list-projects', true);
    makeToolInvocation($this->workspaceId, 'broken-tool', false);

    // Assert on the resolved feed, not the rendered HTML — every tool name also
    // appears in the tool-filter dropdown, so assertDontSee on a name is unsafe.
    $tools = Livewire::test('pages::tool-invocations')
        ->set('resultFilter', 'error')
        ->instance()->invocations->pluck('tool_name');

    expect($tools)->toContain('broken-tool');
    expect($tools)->not->toContain('list-projects');
});

test('the result filter can isolate successes', function () {
    makeToolInvocation($this->workspaceId, 'list-projects', true);
    makeToolInvocation($this->workspaceId, 'broken-tool', false);

    $tools = Livewire::test('pages::tool-invocations')
        ->set('resultFilter', 'ok')
        ->instance()->invocations->pluck('tool_name');

    expect($tools)->toContain('list-projects');
    expect($tools)->not->toContain('broken-tool');
});

test('the transport filter narrows to one transport', function () {
    makeToolInvocation($this->workspaceId, 'stdio-tool', true, 'stdio');
    makeToolInvocation($this->workspaceId, 'http-tool', true, 'http');

    $tools = Livewire::test('pages::tool-invocations')
        ->set('transportFilter', 'http')
        ->instance()->invocations->pluck('tool_name');

    expect($tools)->toContain('http-tool');
    expect($tools)->not->toContain('stdio-tool');
});

test('an empty filter result explains that the filter, not the feed, is empty', function () {
    makeToolInvocation($this->workspaceId, 'list-projects', true);

    Livewire::test('pages::tool-invocations')
        ->set('resultFilter', 'error')
        ->assertSee('No tool invocations match the current filter.');
});

test('filters render in the tool invocations table header', function () {
    makeToolInvocation($this->workspaceId, 'list-projects', true, 'stdio');

    Livewire::test('pages::tool-invocations')
        ->assertSeeInOrder(['Tool invocations', '1 recent', 'All results', 'All transports', 'All tools', 'Time'])
        ->assertSee('lg:flex-row lg:items-center lg:justify-between', false)
        ->assertSee('data-test="tool-invocations-result-filter"', false)
        ->assertSee('data-test="tool-invocations-transport-filter"', false)
        ->assertSee('data-test="tool-invocations-tool-filter"', false);
});

test('clear filters resets the tool invocations feed', function () {
    makeToolInvocation($this->workspaceId, 'list-projects', true, 'stdio');
    makeToolInvocation($this->workspaceId, 'broken-tool', false, 'http');

    Livewire::test('pages::tool-invocations')
        ->set('resultFilter', 'error')
        ->set('transportFilter', 'http')
        ->set('toolFilter', 'broken-tool')
        ->assertSee('Clear filters')
        ->call('clearFilters')
        ->assertSet('resultFilter', 'all')
        ->assertSet('transportFilter', 'all')
        ->assertSet('toolFilter', 'all')
        ->assertSee('list-projects')
        ->assertSee('broken-tool');
});

test('the notification bell links to the full notifications page', function () {
    Livewire::test('notification-bell')
        ->assertSee('View all notifications')
        ->assertSee(route('notifications'), false);
});
