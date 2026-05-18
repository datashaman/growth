<?php

namespace App\Mcp\Tools\Plan;

use App\Models\SpecMockup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description("List a work item's spec mockups — the named layout alternatives held against it. The HTML is omitted; fetch a single mockup to see it.")]
class ListMockups extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
        ]);

        $mockups = SpecMockup::where('work_item_id', $data['work_item_id'])
            ->orderBy('name')
            ->get();

        return Response::structured([
            'work_item_id' => $data['work_item_id'],
            'total' => $mockups->count(),
            'results' => $mockups->map(fn (SpecMockup $mockup): array => [
                'id' => $mockup->id,
                'name' => $mockup->name,
                'updated_at' => $mockup->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->description('Work item ULID whose mockups to list')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->required(),
            'total' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
