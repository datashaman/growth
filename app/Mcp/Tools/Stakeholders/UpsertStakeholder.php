<?php

namespace App\Mcp\Tools\Stakeholders;

use App\Models\Stakeholder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a stakeholder (requirement rules / architecture coverage rules). Stakeholders raise concerns and own requirements.')]
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
            'id' => $schema->string()
                ->description('Existing stakeholder ID. Omit to create new.'),
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'name' => $schema->string()
                ->description('Stakeholder name or class label (e.g. "Operations team", "End user")')
                ->required(),
            'role' => $schema->string()
                ->description('Free-form role hint (e.g. "user", "developer", "regulator", "acquirer")'),
            'kind' => $schema->string()
                ->description('individual person, or a class/group of stakeholders')
                ->enum(['individual', 'class']),
            'description' => $schema->string()
                ->description('Optional notes about the stakeholder'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'kind' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
