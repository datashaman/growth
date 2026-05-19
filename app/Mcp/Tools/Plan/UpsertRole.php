<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Role;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create or update a role that can own work items or fill responsibilities.')]
class UpsertRole extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_role',
            'project_id' => 'required|string|owned_project',
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('roles', 'name')
                    ->where('project_id', $request->get('project_id'))
                    ->ignore($request->get('id')),
            ],
            'responsibilities' => 'nullable|string',
            'persona' => 'nullable|string',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $role = $id ? tap(Role::findOrFail($id))->update($data) : Role::create($data);

        return Response::structured([
            'id' => $role->id,
            'name' => $role->name,
            'created' => $role->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing role ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'name' => $schema->string()->description('Role label, unique per project')->required(),
            'responsibilities' => $schema->string()->description('Responsibilities owned by this role'),
            'persona' => $schema->string()->description('Instruction text served to a session that adopts this role — its accountability, judgement, and what needs user confirmation. Omit to leave the role without a persona.'),
        ];
    }
}
