<?php

use App\Models\McpSession;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleContext;
use Laravel\Mcp\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $this->role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Engineering Lead',
    ]);

    $this->bindSession = fn (string $sessionId) => app()->instance('mcp.request', new Request([], $sessionId));
});

it('resolves the adopted role from the session store', function () {
    ($this->bindSession)('sess-resolve');
    McpSession::create([
        'mcp_session_id' => 'sess-resolve',
        'user_id' => $this->user->id,
        'role_id' => $this->role->id,
    ]);

    expect(app(RoleContext::class)->role()?->id)->toBe($this->role->id);
});

it('returns null outside an MCP request', function () {
    expect(app(RoleContext::class)->role())->toBeNull();
});

it('returns null when the session has adopted no role', function () {
    ($this->bindSession)('sess-empty');

    expect(app(RoleContext::class)->role())->toBeNull();
});

it('does not resolve another user\'s session row', function () {
    $other = User::factory()->create();
    ($this->bindSession)('shared-session-id');
    McpSession::create([
        'mcp_session_id' => 'shared-session-id',
        'user_id' => $other->id,
        'role_id' => $this->role->id,
    ]);

    expect(app(RoleContext::class)->role())->toBeNull();
});

it('lets set() override the resolved role', function () {
    $context = app(RoleContext::class);
    $context->set($this->role);

    expect($context->role()?->id)->toBe($this->role->id);

    $context->forget();

    expect($context->role())->toBeNull();
});

it('adopt() writes the binding to the session store', function () {
    ($this->bindSession)('sess-adopt');

    $session = app(RoleContext::class)->adopt($this->role);

    expect($session->mcp_session_id)->toBe('sess-adopt')
        ->and($session->role_id)->toBe($this->role->id);

    $this->assertDatabaseHas('mcp_sessions', [
        'mcp_session_id' => 'sess-adopt',
        'user_id' => $this->user->id,
        'role_id' => $this->role->id,
    ]);
});

it('adopt() updates the existing row in place', function () {
    ($this->bindSession)('sess-readopt');
    $secondRole = Role::create(['project_id' => $this->project->id, 'name' => 'Product Lead']);

    app(RoleContext::class)->adopt($this->role);
    app(RoleContext::class)->adopt($secondRole);

    expect(McpSession::where('mcp_session_id', 'sess-readopt')->count())->toBe(1)
        ->and(app(RoleContext::class)->role()?->id)->toBe($secondRole->id);
});

it('adopt() fails without an MCP session', function () {
    expect(fn () => app(RoleContext::class)->adopt($this->role))
        ->toThrow(RuntimeException::class);
});

it('prunes session rows idle past the retention window', function () {
    $old = McpSession::create([
        'mcp_session_id' => 'sess-old',
        'user_id' => $this->user->id,
        'role_id' => $this->role->id,
    ]);
    McpSession::whereKey($old->id)->update([
        'updated_at' => now()->subDays(McpSession::PRUNE_AFTER_DAYS + 1),
    ]);
    McpSession::create([
        'mcp_session_id' => 'sess-fresh',
        'user_id' => $this->user->id,
        'role_id' => $this->role->id,
    ]);

    $this->artisan('model:prune', ['--model' => [McpSession::class]])->assertSuccessful();

    expect(McpSession::count())->toBe(1)
        ->and(McpSession::sole()->mcp_session_id)->toBe('sess-fresh');
});
