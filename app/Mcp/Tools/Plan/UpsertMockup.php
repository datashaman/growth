<?php

namespace App\Mcp\Tools\Plan;

use App\Models\SpecMockup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description("Create or replace a work item's spec mockup — a self-contained HTML page expressing a UI idea for the work item.")]
class UpsertMockup extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
            'name' => 'required|string|max:255',
            'html' => 'required|string',
        ]);

        $mockup = SpecMockup::updateOrCreate(
            ['work_item_id' => $data['work_item_id']],
            $data,
        );

        return Response::structured([
            'id' => $mockup->id,
            'work_item_id' => $mockup->work_item_id,
            'name' => $mockup->name,
            'created' => $mockup->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->description('Work item ULID the mockup belongs to')->required(),
            'name' => $schema->string()->description('Short label for the mockup')->required(),
            'html' => $schema->string()->description('A self-contained HTML document — the mockup. Inline the styles and scripts (CDN links are fine); it renders sandboxed, isolated from the Growth app.')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'work_item_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
