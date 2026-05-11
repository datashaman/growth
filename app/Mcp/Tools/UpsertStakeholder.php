<?php

namespace App\Mcp\Tools;

use App\Models\Stakeholder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a stakeholder who contributes intent, constraints, concerns, or acceptance expectations.')]
class UpsertStakeholder extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_stakeholder',
            'project_id' => 'required|string|owned_project',
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:100',
            'kind' => 'nullable|in:individual,class',
            'description' => 'nullable|string',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $stakeholder = $id
            ? tap(Stakeholder::findOrFail($id))->update($data)
            : Stakeholder::create($data + ['kind' => 'individual']);

        return Response::structured([
            'id' => $stakeholder->id,
            'name' => $stakeholder->name,
            'kind' => $stakeholder->kind,
            'created' => $stakeholder->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing stakeholder ULID. Omit to create new.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'name' => $schema->string()->description('Stakeholder name or group label')->required(),
            'role' => $schema->string()->description('Role in this project'),
            'kind' => $schema->string()->description('Stakeholder kind')->enum(['individual', 'class']),
            'description' => $schema->string()->description('Optional notes about this stakeholder'),
        ];
    }
}
