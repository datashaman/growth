<?php

namespace App\Mcp\Tools\Sources;

use App\Models\Citation;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a citation link from a source to a project artifact.')]
class UpsertCitation extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $types = array_keys(AppServiceProvider::MORPH_MAP);

        $data = $request->validate([
            'id' => 'nullable|string|owned_citation',
            'source_id' => 'required|string|owned_source',
            'citable_type' => 'required|string|in:'.implode(',', $types),
            'citable_id' => 'required|string',
            'quote' => 'nullable|string',
            'locator' => 'nullable|string|max:255',
        ]);

        $request->validate([
            'citable_id' => 'required|string|owned_'.$data['citable_type'],
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $citation = $id
            ? tap(Citation::findOrFail($id))->update($data)
            : Citation::firstOrCreate(
                [
                    'source_id' => $data['source_id'],
                    'citable_type' => $data['citable_type'],
                    'citable_id' => $data['citable_id'],
                    'locator' => $data['locator'] ?? null,
                ],
                ['quote' => $data['quote'] ?? null],
            );

        return Response::structured([
            'id' => $citation->id,
            'source_id' => $citation->source_id,
            'citable_type' => $citation->citable_type,
            'citable_id' => $citation->citable_id,
            'created' => $citation->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing citation ULID. Omit to create or find by source/artifact/locator.'),
            'source_id' => $schema->string()->description('Source ULID this citation points back to')->required(),
            'citable_type' => $schema->string()->description('Artifact kind being cited')->enum(array_keys(AppServiceProvider::MORPH_MAP))->required(),
            'citable_id' => $schema->string()->description('ULID of the artifact being cited')->required(),
            'quote' => $schema->string()->description('Optional excerpt from the source'),
            'locator' => $schema->string()->description('Where inside the source, such as page, section, frame, or timestamp'),
        ];
    }
}
