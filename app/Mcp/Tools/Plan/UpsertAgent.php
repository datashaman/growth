<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Agent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a specialized Agent that can fill one or more Roles in this project. An Agent represents an automated worker (LLM, bot, integration) addressable as a role-filler. Agent names are unique per project.')]
class UpsertAgent extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_agent',
            'project_id' => 'required|string|owned_project',
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('agents', 'name')
                    ->where('project_id', $request->get('project_id'))
                    ->ignore($request->get('id')),
            ],
            'kind' => 'nullable|string|max:60',
            'description' => 'nullable|string',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $agent = $id
            ? tap(Agent::findOrFail($id))->update($data)
            : Agent::create($data);

        return Response::structured([
            'id' => $agent->id,
            'name' => $agent->name,
            'kind' => $agent->kind,
            'created' => $agent->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Existing agent ULID. Omit to create.'),
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'name' => $schema->string()
                ->description('Agent name, unique per project (e.g. "qa-bot", "growth-helper-v2")')
                ->required(),
            'kind' => $schema->string()
                ->description('Specialty / requirement tag (free-form, e.g. "qa", "design", "code-review")'),
            'description' => $schema->string()
                ->description('What this agent does and its addressable contract'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'kind' => $schema->string(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
