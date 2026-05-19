<?php

namespace App\Mcp\Tools\Common;

use App\Models\Agent;
use App\Models\Project;
use App\Models\User;
use App\Support\AgentContext;
use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Passport\AccessToken;
use Throwable;

#[IsReadOnly]
#[Description('Diagnose this MCP session: report authentication, the active workspace and how it resolved, token scope/expiry/workspace binding, and local-session env vars. Each check is `pass`, `warn`, `fail`, or `not_applicable`, with a remedy. This tool never errors — call it when other tools fail unexpectedly.')]
class Doctor extends Tool
{
    private const SEVERITY = ['not_applicable' => 0, 'pass' => 1, 'warn' => 2, 'fail' => 3];

    public function handle(Request $request): ResponseFactory
    {
        /** @var User|null $user */
        $user = auth()->user();
        $token = $user instanceof User ? $this->safe(fn () => $user->token()) : null;
        $transport = $token instanceof AccessToken ? 'http' : 'stdio';

        $checks = [
            $this->authenticationCheck($user),
            $this->workspaceCheck(),
            $this->tokenBindingCheck($transport, $token),
            $this->scopeCheck($transport, $token),
            $this->expiryCheck($transport, $token),
            $this->localSessionCheck($transport, $user),
            $this->projectsCheck(),
            $this->agentCheck(),
        ];

        return Response::structured([
            'transport' => $transport,
            'authenticated' => $user instanceof User,
            'overall' => $this->overall($checks),
            'checks' => $checks,
        ]);
    }

    /**
     * @return array{check:string,status:string,detail:string}
     */
    private function authenticationCheck(?User $user): array
    {
        if ($user instanceof User) {
            return $this->result('authentication', 'pass', "Authenticated as {$user->email} (user #{$user->id}).");
        }

        return $this->result('authentication', 'fail',
            'No authenticated user. For local stdio set GROWTH_USER_EMAIL or GROWTH_USER_ID; for HTTP send a valid bearer token.');
    }

    /**
     * @return array{check:string,status:string,detail:string}
     */
    private function workspaceCheck(): array
    {
        $context = app(WorkspaceContext::class);
        $workspaceId = $this->safe(fn () => $context->id());
        $source = $this->safe(fn () => $context->source());

        if (is_string($workspaceId)) {
            return $this->result('active_workspace', 'pass', "Workspace {$workspaceId} is active (resolved via {$source}).");
        }

        return $this->result('active_workspace', 'fail',
            'No workspace is bound. Bind the token to a workspace, set GROWTH_WORKSPACE_ID, or set the user\'s active workspace.');
    }

    /**
     * @return array{check:string,status:string,detail:string}
     */
    private function tokenBindingCheck(string $transport, ?AccessToken $token): array
    {
        if ($transport !== 'http' || ! $token instanceof AccessToken) {
            return $this->result('workspace_token_binding', 'not_applicable', 'Local stdio session — tokens are not used.');
        }

        $boundWorkspaceId = $this->safe(fn () => $token->workspace_id);

        if (is_string($boundWorkspaceId) && $boundWorkspaceId !== '') {
            return $this->result('workspace_token_binding', 'pass', "Token is bound to workspace {$boundWorkspaceId}.");
        }

        return $this->result('workspace_token_binding', 'warn',
            'Token is not bound to a workspace; the session is relying on a fallback. Issue a workspace-bound token for predictable scoping.');
    }

    /**
     * @return array{check:string,status:string,detail:string}
     */
    private function scopeCheck(string $transport, ?AccessToken $token): array
    {
        if ($transport !== 'http' || ! $token instanceof AccessToken) {
            return $this->result('mcp_scope', 'not_applicable', 'Local stdio session — tokens are not used.');
        }

        if ($this->safe(fn () => $token->can('mcp:use')) === true) {
            return $this->result('mcp_scope', 'pass', 'Token carries the mcp:use scope.');
        }

        return $this->result('mcp_scope', 'fail', 'Token is missing the mcp:use scope. Re-issue the token with `mcp:use`.');
    }

    /**
     * @return array{check:string,status:string,detail:string}
     */
    private function expiryCheck(string $transport, ?AccessToken $token): array
    {
        if ($transport !== 'http' || ! $token instanceof AccessToken) {
            return $this->result('token_expiry', 'not_applicable', 'Local stdio session — tokens are not used.');
        }

        $expiresAt = $this->safe(fn () => $token->expires_at);

        if ($expiresAt === null) {
            return $this->result('token_expiry', 'pass', 'Token has no expiry.');
        }

        if ($expiresAt->isPast()) {
            return $this->result('token_expiry', 'fail', "Token expired {$expiresAt->diffForHumans()}. Re-issue it.");
        }

        if ($expiresAt->lessThanOrEqualTo(now()->addDays(7))) {
            return $this->result('token_expiry', 'warn', "Token expires {$expiresAt->diffForHumans()}. Re-issue it soon.");
        }

        return $this->result('token_expiry', 'pass', "Token expires {$expiresAt->diffForHumans()}.");
    }

    /**
     * @return array{check:string,status:string,detail:string}
     */
    private function localSessionCheck(string $transport, ?User $user): array
    {
        if ($transport !== 'stdio') {
            return $this->result('local_session_env', 'not_applicable', 'HTTP session — local env vars are not used.');
        }

        $userId = (string) env('GROWTH_USER_ID', '');
        $userEmail = (string) env('GROWTH_USER_EMAIL', '');

        if ($userId !== '') {
            return $this->result('local_session_env', 'pass', 'GROWTH_USER_ID is set.');
        }

        if ($userEmail !== '') {
            return $this->result('local_session_env', 'pass', 'GROWTH_USER_EMAIL is set.');
        }

        if ($user instanceof User) {
            return $this->result('local_session_env', 'pass', 'A user is bound to this session.');
        }

        return $this->result('local_session_env', 'fail',
            'Neither GROWTH_USER_EMAIL nor GROWTH_USER_ID is set; the session is unauthenticated.');
    }

    /**
     * @return array{check:string,status:string,detail:string}
     */
    private function projectsCheck(): array
    {
        if ($this->safe(fn () => app(WorkspaceContext::class)->id()) === null) {
            return $this->result('workspace_projects', 'not_applicable', 'No workspace is bound to count projects in.');
        }

        $count = $this->safe(fn () => Project::count());

        if ($count === null) {
            return $this->result('workspace_projects', 'fail', 'Could not read projects for the active workspace.');
        }

        if ($count === 0) {
            return $this->result('workspace_projects', 'warn', 'The active workspace has no projects yet.');
        }

        return $this->result('workspace_projects', 'pass', "The active workspace has {$count} project(s).");
    }

    /**
     * Report the agent this session is acting as (#295). An unbound agent is
     * a normal state — a user acting directly — so it passes; a bound id that
     * does not resolve is a stale binding and warns.
     *
     * @return array{check:string,status:string,detail:string}
     */
    private function agentCheck(): array
    {
        $context = app(AgentContext::class);
        $agent = $this->safe(fn () => $context->agent());
        $source = $this->safe(fn () => $context->source());

        if ($agent instanceof Agent) {
            return $this->result('acting_agent', 'pass',
                "Acting as agent \"{$agent->name}\" ({$agent->id}), resolved via {$source}.");
        }

        if (is_string($source) && $source !== '') {
            return $this->result('acting_agent', 'warn',
                'An agent is bound to this session but did not resolve — the id is unknown, in another workspace, or deleted. Re-bind to a valid agent or clear it.');
        }

        return $this->result('acting_agent', 'pass',
            'No agent is bound — acting as the user directly.');
    }

    /**
     * @param  list<array{check:string,status:string,detail:string}>  $checks
     */
    private function overall(array $checks): string
    {
        $worst = 'pass';

        foreach ($checks as $check) {
            if (self::SEVERITY[$check['status']] > self::SEVERITY[$worst]) {
                $worst = $check['status'];
            }
        }

        return $worst === 'not_applicable' ? 'pass' : $worst;
    }

    /**
     * Run a callback, swallowing any failure so the diagnostic itself never throws.
     */
    private function safe(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{check:string,status:string,detail:string}
     */
    private function result(string $check, string $status, string $detail): array
    {
        return ['check' => $check, 'status' => $status, 'detail' => $detail];
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'transport' => $schema->string()->required(),
            'authenticated' => $schema->boolean()->required(),
            'overall' => $schema->string()->required(),
            'checks' => $schema->array()->required(),
        ];
    }
}
