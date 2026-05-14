<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Deployment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a deployment record and detach linked delivery evidence.')]
class DeleteDeployment extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['id' => 'required|string|owned_deployment']);
        $deployment = Deployment::findOrFail($data['id']);
        $links = $deployment->deliveryLinks()->count();
        $deployment->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'delivery_links_detached' => $links,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->description('Deployment ULID')->required()];
    }
}
