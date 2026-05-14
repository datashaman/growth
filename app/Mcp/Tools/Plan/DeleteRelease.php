<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Release;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a release. Linked work items and deployments survive but lose their release_id (detached, not deleted). Requires confirm_name to match the release name exactly.')]
class DeleteRelease extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_release',
            'confirm_name' => 'required|string',
        ]);

        $release = Release::findOrFail($data['id']);

        if ($data['confirm_name'] !== $release->name) {
            return new ResponseFactory(Response::error(
                "Confirmation mismatch. Pass the release's exact name in `confirm_name` to delete it. Release is named [{$release->name}]."
            ));
        }

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
        return [
            'id' => $schema->string()->description('Release ULID')->required(),
            'confirm_name' => $schema->string()
                ->description('Must match the release name exactly to guard against accidental deletion')
                ->required(),
        ];
    }
}
