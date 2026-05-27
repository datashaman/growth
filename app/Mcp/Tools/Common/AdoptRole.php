<?php

namespace App\Mcp\Tools\Common;

use App\Models\Role;
use App\Support\AgentContext;
use App\Support\RoleContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Adopt a project role for the rest of this session. Call this first, before doing project work: it pins the role, returns its persona — what the role is accountable for, the judgement it brings, what is routine versus what needs your user\'s confirmation — and stamps the role onto everything the session records. You must already be assigned to the role (see assign-roles). The persona is advisory: you decide whether to honour it.')]
class AdoptRole extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'role_id' => 'required|string|owned_role',
        ]);

        $role = Role::findOrFail($data['role_id']);

        $this->assertAssigned($role);

        app(RoleContext::class)->adopt($role);

        $role->load('capabilityAssignments');

        return Response::structured([
            'role_id' => $role->id,
            'name' => $role->name,
            'persona' => $role->persona,
            'capabilities' => $role->capabilities()->map->value->all(),
        ]);
    }

    /**
     * The session's effective principal must hold the role before it can act
     * under it — otherwise the audit trail's `acting_role_id` would be a claim
     * nobody can back. The principal is the bound Agent when the session has
     * one, otherwise the authenticated User.
     */
    private function assertAssigned(Role $role): void
    {
        $agent = app(AgentContext::class)->agent();

        $assigned = $agent !== null
            ? $agent->roles()->whereKey($role->getKey())->exists()
            : $role->users()->whereKey(auth()->id())->exists();

        if (! $assigned) {
            throw ValidationException::withMessages([
                'role_id' => $agent !== null
                    ? "Agent {$agent->name} is not assigned to this role — use assign-roles first."
                    : 'You are not assigned to this role — use assign-roles first.',
            ]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'role_id' => $schema->string()
                ->description('Role ULID to adopt. You must already be assigned to it.')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'role_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'persona' => $schema->string()
                ->description('The role persona, or null when the role carries none.'),
            'capabilities' => $schema->array()
                ->description('Capability slugs carried by the adopted role.')
                ->items($schema->string())
                ->required(),
        ];
    }
}
