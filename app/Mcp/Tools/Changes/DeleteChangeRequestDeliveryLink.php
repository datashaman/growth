<?php

namespace App\Mcp\Tools\Changes;

use App\Models\ChangeRequestDeliveryLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete an implementation evidence link from a change request without affecting work-item delivery links.')]
class DeleteChangeRequestDeliveryLink extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_change_request_delivery_link',
        ]);

        $link = ChangeRequestDeliveryLink::findOrFail($data['id']);
        $payload = [
            'id' => $link->id,
            'change_request_id' => $link->change_request_id,
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
            'id' => $schema->string()->description('Change-request delivery link ULID to delete')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'change_request_id' => $schema->string()->required(),
            'type' => $schema->string()->required(),
            'ref' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
        ];
    }
}
