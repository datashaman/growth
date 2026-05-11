<?php

namespace App\Mcp\Tools\Sources;

use App\Models\Citation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Remove a citation. Neither the source nor the cited artifact is affected.')]
class UncitArtifact extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'citation_id' => 'required|string|owned_citation',
        ]);

        $citation = Citation::findOrFail($data['citation_id']);
        $citation->delete();

        return Response::structured([
            'id' => $data['citation_id'],
            'deleted' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'citation_id' => $schema->string()
                ->description('Citation ULID to remove')
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
