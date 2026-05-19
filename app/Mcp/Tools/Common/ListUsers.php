<?php

namespace App\Mcp\Tools\Common;

use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the members of the active workspace — their user_id, name, email, and workspace role. Use this to find the user_id for send-notification or assign-role.')]
class ListUsers extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $workspace = Workspace::findOrFail(app(WorkspaceContext::class)->requireId());

        $members = $workspace->members()->orderBy('name')->get();

        return Response::structured([
            'workspace_id' => $workspace->id,
            'total' => $members->count(),
            'results' => $members->map(fn (User $member): array => [
                'user_id' => $member->getKey(),
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
            'total' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
