<?php

namespace App\Mcp\Tools;

use App\Models\Release;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a release record and detach linked work items.')]
class DeleteRelease extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['id' => 'required|string|owned_release']);
        $release = Release::findOrFail($data['id']);
        $workItems = $release->workItems()->count();
        $deployments = $release->deployments()->count();
        $release->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'work_items_detached' => $workItems,
            'deployments_detached' => $deployments,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->description('Release ULID')->required()];
    }
}
