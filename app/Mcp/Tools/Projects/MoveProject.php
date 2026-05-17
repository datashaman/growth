<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Description('Move a project to another workspace. The caller must be an owner or admin of both the current workspace and the destination, and the destination must differ from the current workspace. This reassigns the project only — it does not change which workspace your session is pointed at.')]
class MoveProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_project',
            'destination_workspace_id' => 'required|string',
        ]);

        $project = Project::findOrFail($data['id']);

        try {
            $project->move($data['destination_workspace_id'], auth()->user());
        } catch (HttpException $e) {
            return new ResponseFactory(Response::error($this->rejectionMessage($e)));
        }

        return Response::structured([
            'id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'moved' => true,
        ]);
    }

    /**
     * Translate the HTTP aborts raised by {@see Project::move()} into a clear,
     * agent-facing message.
     */
    private function rejectionMessage(HttpException $e): string
    {
        if ($e->getStatusCode() === 422 && $e->getMessage() !== '') {
            return $e->getMessage();
        }

        return 'Cannot move the project: you must be an owner or admin of both the current workspace and the destination workspace.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Project ULID to move')
                ->required(),
            'destination_workspace_id' => $schema->string()
                ->description('Workspace ULID to move the project into. Must differ from the current workspace, and you must be an owner or admin there.')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'workspace_id' => $schema->string()->required(),
            'moved' => $schema->boolean()->required(),
        ];
    }
}
