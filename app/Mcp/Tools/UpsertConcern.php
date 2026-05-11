<?php

namespace App\Mcp\Tools;

use App\Models\Concern;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a stakeholder concern that architecture and delivery work should address.')]
class UpsertConcern extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_concern',
            'project_id' => 'required|string|owned_project',
            'raised_by_stakeholder_id' => 'nullable|string|owned_stakeholder',
            'text' => 'required|string|min:3',
            'suggested_viewpoints' => 'nullable|array',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $concern = $id
            ? tap(Concern::findOrFail($id))->update($data)
            : Concern::create($data);

        return Response::structured([
            'id' => $concern->id,
            'created' => $concern->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing concern ULID. Omit to create new.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'raised_by_stakeholder_id' => $schema->string()->description('Stakeholder ULID that raised this concern'),
            'text' => $schema->string()->description('Concern statement')->required(),
            'suggested_viewpoints' => $schema->array()->description('Optional architecture viewpoints that may address this concern'),
        ];
    }
}
