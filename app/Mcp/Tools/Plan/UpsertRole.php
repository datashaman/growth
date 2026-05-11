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

#[Description('Create or update an abstract role (e.g. "QA Lead", "Tech Lead"). Roles attach to work items as responsible parties. Role names are unique per project.')]
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
            'weekly_capacity_hours' => 'nullable|numeric|min:0|max:10000',
            'hourly_rate_amount' => 'nullable|numeric|min:0|max:9999999999',
            'rate_currency' => 'nullable|string|size:3',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $role = $id
            ? tap(Role::findOrFail($id))->update($data)
            : Role::create($data);

        return Response::structured([
            'id' => $role->id,
            'name' => $role->name,
            'weekly_capacity_hours' => $role->weekly_capacity_hours,
            'hourly_rate_amount' => $role->hourly_rate_amount,
            'rate_currency' => $role->rate_currency,
            'created' => $role->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Existing role ULID. Omit to create.'),
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'name' => $schema->string()
                ->description('Role label, unique per project')
                ->required(),
            'responsibilities' => $schema->string()
                ->description('What this role is on the hook for'),
            'weekly_capacity_hours' => $schema->number()
                ->description('Available capacity for this role in hours per week'),
            'hourly_rate_amount' => $schema->number()
                ->description('Planning rate used to estimate cost from effort hours'),
            'rate_currency' => $schema->string()
                ->description('ISO-style currency code for the planning rate, e.g. USD'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'weekly_capacity_hours' => $schema->number(),
            'hourly_rate_amount' => $schema->number(),
            'rate_currency' => $schema->string(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
