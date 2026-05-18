<?php

namespace App\Mcp\Tools\Plan;

use App\Models\SpecMockup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a spec mockup from the work item or requirement it belongs to.')]
class DeleteMockup extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_mockup',
        ]);

        SpecMockup::findOrFail($data['id'])->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Spec mockup ULID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
        ];
    }
}
