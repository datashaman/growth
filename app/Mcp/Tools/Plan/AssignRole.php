<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RoleAssigned;
use App\Notifications\WorkspaceNotifier;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Assign one or more roles to people (assignee_type=user) or agents (assignee_type=agent). Existing single-call arguments are supported; batch calls may pass explicit pairs or role_ids with one assignee. Each batch pair is committed independently and reports attached, already_assigned, or error.')]
class AssignRole extends Tool
{
    private const ASSIGNEE_TYPES = ['user', 'agent'];

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'role_id' => 'nullable|string',
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
            return new ResponseFactory(Response::error('Provide either role_id with assignee_type and assignee_id, role_ids with assignee_type and assignee_id, or pairs.'));
        }

        if (count($pairs) === 1 && ! isset($data['pairs']) && ! isset($data['role_ids'])) {
            return $this->singleResponse($pairs[0]);
        }

        return Response::structured([
            'results' => array_map(fn (array $pair): array => $this->assignPair($pair), $pairs),
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

        if (isset($data['role_id'], $data['assignee_type'], $data['assignee_id'])) {
            return [[
                'role_id' => $data['role_id'],
                'assignee_type' => $data['assignee_type'],
                'assignee_id' => $data['assignee_id'],
            ]];
        }

        return [];
    }

    /**
     * @param  array{role_id:string,assignee_type:string,assignee_id:mixed}  $pair
     */
    private function singleResponse(array $pair): ResponseFactory
    {
        $result = $this->assignPair($pair);

        if (($result['ok'] ?? false) !== true) {
            return new ResponseFactory(Response::error($result['message']));
        }

        return Response::structured([
            'role_id' => $result['role_id'],
            'assignee_type' => $result['assignee_type'],
            'assignee_id' => $result['assignee_id'],
            'attached' => $result['attached'],
        ]);
    }

    /**
     * @param  array{role_id:string,assignee_type:string,assignee_id:mixed}  $pair
     * @return array<string,mixed>
     */
    private function assignPair(array $pair): array
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
                'attached' => false,
                'message' => 'assignee_type must be user or agent.',
            ];
        }

        $role = Role::find($pair['role_id']);
        if ($role === null) {
            return $base + [
                'ok' => false,
                'status' => 'error',
                'attached' => false,
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
                'attached' => false,
                'message' => 'Assignee not found in the active workspace.',
            ];
        }

        $relation = $pair['assignee_type'] === 'user' ? 'users' : 'agents';
        $result = $role->{$relation}()->syncWithoutDetaching([$pair['assignee_id']]);
        $attached = $result['attached'] !== [];

        // Tell a user when they are newly assigned to a role — a personal
        // catalogue event. Re-syncing an existing assignment notifies no one.
        if ($pair['assignee_type'] === 'user' && $attached && $assignee instanceof User) {
            app(WorkspaceNotifier::class)->notifyUser($assignee, new RoleAssigned($role));
        }

        return $base + [
            'ok' => true,
            'status' => $attached ? 'attached' : 'already_assigned',
            'attached' => $attached,
            'message' => $attached ? 'Assigned.' : 'Already assigned.',
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'role_id' => $schema->string()
                ->description('Role ULID to fill for a single assignment'),
            'role_ids' => $schema->array()
                ->description('Batch shorthand: assign one assignee to each listed Role ULID'),
            'assignee_type' => $schema->string()
                ->description('user (person) or agent (specialized worker)')
                ->enum(self::ASSIGNEE_TYPES),
            'assignee_id' => $schema->string()
                ->description('Identifier of the assignee filling the role. For assignee_type=user this is the integer user id reported as `user_id` by who-am-i (pass who-am-i\'s `user_id` to self-assign). For assignee_type=agent this is the agent ULID.'),
            'pairs' => $schema->array()
                ->description('Explicit batch pairs; each item has role_id, assignee_type, and assignee_id. Pairs commit independently and return per-pair results.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'role_id' => $schema->string()->required(),
            'assignee_type' => $schema->string()->required(),
            'assignee_id' => $schema->string()->required(),
            'attached' => $schema->boolean()->required(),
            'results' => $schema->array(),
        ];
    }
}
