<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Role;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Remove a role assignment. Neither the role nor the assignee is deleted.')]
class UnassignRole extends Tool
{
    private const ASSIGNEE_TYPES = ['user', 'agent'];

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'role_id' => 'required|string|owned_role',
            'assignee_type' => 'required|in:'.implode(',', self::ASSIGNEE_TYPES),
            'assignee_id' => 'required',
        ]);

        $role = Role::findOrFail($data['role_id']);
        $relation = $data['assignee_type'] === 'user' ? 'users' : 'agents';
        $detached = $role->{$relation}()->detach($data['assignee_id']);

        return Response::structured([
            'role_id' => $role->id,
            'assignee_type' => $data['assignee_type'],
            'assignee_id' => $data['assignee_id'],
            'detached' => $detached > 0,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'role_id' => $schema->string()->description('Role ULID')->required(),
            'assignee_type' => $schema->string()->description('user or agent')->enum(self::ASSIGNEE_TYPES)->required(),
            'assignee_id' => $schema->string()->description('Identifier of the assignee to detach. For assignee_type=user this is the integer user id reported as `user_id` by who-am-i; for assignee_type=agent this is the agent ULID.')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'role_id' => $schema->string()->required(),
            'assignee_type' => $schema->string()->required(),
            'assignee_id' => $schema->string()->required(),
            'detached' => $schema->boolean()->required(),
        ];
    }
}
