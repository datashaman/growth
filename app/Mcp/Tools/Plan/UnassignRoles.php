<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Remove role assignments. Pass explicit pairs or role_ids with one assignee. Each pair is committed independently and reports detached, not_assigned, or error. Neither the role nor the assignee is deleted.')]
class UnassignRoles extends Tool
{
    private const ASSIGNEE_TYPES = ['user', 'agent'];

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'role_ids' => 'nullable|array|min:1|max:100',
            'role_ids.*' => 'required|string',
            'assignee_type' => 'nullable|string',
            'assignee_id' => 'nullable',
            'pairs' => 'nullable|array|min:1|max:100',
            'pairs.*.role_id' => 'required|string',
            'pairs.*.assignee_type' => 'required|string',
            'pairs.*.assignee_id' => 'required',
        ]);

        $pairs = $this->pairsFrom($data);
        if ($pairs === []) {
            return new ResponseFactory(Response::error('Provide either role_ids with assignee_type and assignee_id, or pairs.'));
        }

        return Response::structured([
            'results' => array_map(fn (array $pair): array => $this->unassignPair($pair), $pairs),
        ]);
    }

    /**
     * @param  array<string,mixed>  $data
     * @return list<array{role_id:string,assignee_type:string,assignee_id:mixed}>
     */
    private function pairsFrom(array $data): array
    {
        if (isset($data['pairs'])) {
            return array_values($data['pairs']);
        }

        if (isset($data['role_ids']) && isset($data['assignee_type'], $data['assignee_id'])) {
            return array_map(fn (string $roleId): array => [
                'role_id' => $roleId,
                'assignee_type' => $data['assignee_type'],
                'assignee_id' => $data['assignee_id'],
            ], array_values($data['role_ids']));
        }

        return [];
    }

    /**
     * @param  array{role_id:string,assignee_type:string,assignee_id:mixed}  $pair
     * @return array<string,mixed>
     */
    private function unassignPair(array $pair): array
    {
        $base = [
            'role_id' => $pair['role_id'],
            'assignee_type' => $pair['assignee_type'],
            'assignee_id' => $pair['assignee_id'],
        ];

        if (! in_array($pair['assignee_type'], self::ASSIGNEE_TYPES, true)) {
            return $base + [
                'ok' => false,
                'status' => 'error',
                'detached' => false,
                'message' => 'assignee_type must be user or agent.',
            ];
        }

        $role = Role::find($pair['role_id']);
        if ($role === null) {
            return $base + [
                'ok' => false,
                'status' => 'error',
                'detached' => false,
                'message' => 'Role not found in the active workspace.',
            ];
        }

        $assignee = $pair['assignee_type'] === 'user'
            ? User::find($pair['assignee_id'])
            : Agent::find($pair['assignee_id']);

        if ($assignee === null) {
            return $base + [
                'ok' => false,
                'status' => 'error',
                'detached' => false,
                'message' => 'Assignee not found in the active workspace.',
            ];
        }

        $relation = $pair['assignee_type'] === 'user' ? 'users' : 'agents';
        $detached = $role->{$relation}()->detach($pair['assignee_id']) > 0;

        return $base + [
            'ok' => true,
            'status' => $detached ? 'detached' : 'not_assigned',
            'detached' => $detached,
            'message' => $detached ? 'Unassigned.' : 'Not assigned.',
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'role_ids' => $schema->array()->description('Unassign one assignee from each listed Role ULID'),
            'assignee_type' => $schema->string()->description('user or agent')->enum(self::ASSIGNEE_TYPES),
            'assignee_id' => $schema->string()->description('Identifier of the assignee to detach. For assignee_type=user this is the integer user id reported as `user_id` by who-am-i; for assignee_type=agent this is the agent ULID.'),
            'pairs' => $schema->array()->description('Explicit role unassignment pairs; each item has role_id, assignee_type, and assignee_id. Pairs commit independently and return per-pair results.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()->required(),
        ];
    }
}
