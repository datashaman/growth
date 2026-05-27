<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItemDeliveryLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete an implementation evidence link from a work item and clean up any dependent Growth-hosted evidence assets.')]
class DeleteDeliveryLink extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_work_item_delivery_link',
        ]);

        $link = WorkItemDeliveryLink::findOrFail($data['id']);
        $payload = [
            'id' => $link->id,
            'work_item_id' => $link->work_item_id,
            'type' => $link->type,
            'ref' => $link->ref,
            'deleted' => true,
        ];

        $link->delete();

        return Response::structured($payload);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Work-item delivery link ULID to delete')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'work_item_id' => $schema->string()->required(),
            'type' => $schema->string()->required(),
            'ref' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
        ];
    }
}
