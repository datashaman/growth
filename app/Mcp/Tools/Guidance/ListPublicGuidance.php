<?php

namespace App\Mcp\Tools\Guidance;

use App\Growth\Guidance\PublicGuidanceCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List public NASA/NIST guidance sources available for internal rule-pack extraction.')]
class ListPublicGuidance extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string',
        ]);

        $rows = collect(PublicGuidanceCatalog::SOURCES)
            ->when(isset($data['id']), fn ($sources) => $sources->only($data['id']))
            ->map(fn (array $source, string $id) => [
                'id' => $id,
                'title' => $source['title'],
                'publisher' => $source['publisher'],
                'source_url' => $source['source_url'],
                'license_status' => $source['license_status'],
                'resource_uri' => "growth://guidance/{$id}",
                'text_available' => Storage::disk('local')->exists("growth/public-guidance/{$id}.txt"),
                'rule_pack_opportunities' => $source['rule_pack_opportunities'],
            ])
            ->values();

        if (isset($data['id']) && $rows->isEmpty()) {
            return Response::structured([
                'error' => "Guidance source [{$data['id']}] not supported.",
                'total' => 0,
                'results' => [],
            ]);
        }

        return Response::structured([
            'total' => $rows->count(),
            'results' => $rows->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Optional guidance id, e.g. nasa-seh, nasa-risk, nist-ssdf'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'total' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
