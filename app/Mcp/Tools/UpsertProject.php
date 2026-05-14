<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a Growth project. Omit id to create; provide id to update.')]
class UpsertProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_project',
            'name' => 'required_without:id|string|max:255',
            'description' => 'nullable|string',
            'rigor_level' => 'nullable|integer|between:1,4',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $project = $id
            ? tap(Project::findOrFail($id))->update($data)
            : Project::create($data + [
                'rigor_level' => 2,
                'user_id' => auth()->id(),
            ]);

        return Response::structured([
            'id' => $project->id,
            'name' => $project->name,
            'rigor_level' => $project->rigor_level,
            'created' => $project->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing project ULID. Omit to create new.'),
            'name' => $schema->string()->description('Project name. Required when creating.'),
            'description' => $schema->string()->description('Optional project description'),
            'rigor_level' => $schema->integer()->description('AI-delivery rigor level: 1 low to 4 high. Defaults to 2 on create.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'rigor_level' => $schema->integer()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
