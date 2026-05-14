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

#[Description('List citations by source or cited artifact.')]
class ListCitations extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $types = array_keys(AppServiceProvider::MORPH_MAP);

        $data = $request->validate([
            'source_id' => 'nullable|string|owned_source',
            'citable_type' => 'nullable|required_with:citable_id|string|in:'.implode(',', $types),
            'citable_id' => 'nullable|required_with:citable_type|string',
        ]);

        if (isset($data['citable_type'], $data['citable_id'])) {
            $request->validate([
                'citable_id' => 'required|string|owned_'.$data['citable_type'],
            ]);
        }

        $query = Citation::query()->with('source:id,title,kind');

        if (isset($data['source_id'])) {
            $query->where('source_id', $data['source_id']);
        }
        if (isset($data['citable_type'], $data['citable_id'])) {
            $query->where('citable_type', $data['citable_type'])
                ->where('citable_id', $data['citable_id']);
        }

        return Response::structured([
            'results' => $query->orderBy('created_at')->get()->map(fn ($citation) => [
                'id' => $citation->id,
                'source_id' => $citation->source_id,
                'source_title' => $citation->source?->title,
                'citable_type' => $citation->citable_type,
                'citable_id' => $citation->citable_id,
                'locator' => $citation->locator,
                'quote' => $citation->quote,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source_id' => $schema->string()->description('Filter by source ULID'),
            'citable_type' => $schema->string()->description('Artifact kind being cited')->enum(array_keys(AppServiceProvider::MORPH_MAP)),
            'citable_id' => $schema->string()->description('ULID of the cited artifact'),
        ];
    }
}
