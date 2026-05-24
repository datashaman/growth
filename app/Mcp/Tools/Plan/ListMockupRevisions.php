<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Mockup;
use App\Models\MockupRevision;
use App\Support\MockupScreenshotAsset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("List a spec mockup's revisions — the ordered rounds of its HTML. The HTML itself is omitted; the highest number is the mockup's current state.")]
class ListMockupRevisions extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'mockup_id' => 'required|string|owned_mockup',
        ]);

        $mockup = Mockup::findOrFail($data['mockup_id']);
        $revisions = $mockup->revisions()->get();

        return Response::structured([
            'mockup_id' => $mockup->id,
            'name' => $mockup->name,
            'total' => $revisions->count(),
            'current_revision' => $revisions->max('number'),
            'results' => $revisions->map(fn (MockupRevision $revision): array => [
                'id' => $revision->id,
                'number' => $revision->number,
                'created_at' => $revision->created_at?->toIso8601String(),
                'uri' => "growth://mockups/{$mockup->id}/{$revision->id}",
                'html_uri' => "growth://mockups/{$mockup->id}/{$revision->id}/html",
                'preview_uri' => "growth://mockups/{$mockup->id}/{$revision->id}/preview",
                'screenshot_asset' => app(MockupScreenshotAsset::class)->reference($mockup, $revision),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'mockup_id' => $schema->string()->description('Spec mockup ULID whose revisions to list')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'mockup_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'total' => $schema->integer()->required(),
            'current_revision' => $schema->integer(),
            'results' => $schema->array()->required(),
        ];
    }
}
