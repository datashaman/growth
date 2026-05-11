<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Agent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete an agent. Role assignments for this agent are removed; roles themselves are untouched.')]
class DeleteAgent extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_agent',
        ]);

        $agent = Agent::findOrFail($data['id']);
        $unassigned = $agent->roles()->count();
        $agent->roles()->detach();
        $agent->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'roles_unassigned' => $unassigned,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Agent ULID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'roles_unassigned' => $schema->integer()->required(),
        ];
    }
}
