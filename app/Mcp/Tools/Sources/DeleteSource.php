<?php

namespace App\Mcp\Tools\Sources;

use App\Models\Source;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a source. All citations from this source are cascade-removed; the cited artifacts themselves are untouched.')]
class DeleteSource extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_source',
        ]);

        $source = Source::findOrFail($data['id']);
        $unlinked = $source->citations()->count();
        $source->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'citations_unlinked' => $unlinked,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Source ULID to delete')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'citations_unlinked' => $schema->integer()->required(),
        ];
    }
}
