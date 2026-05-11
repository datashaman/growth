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

#[Description('Search locally extracted public NASA/NIST guidance text. Returns bounded snippets only.')]
class SearchPublicGuidance extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'query' => 'required|string|min:2|max:120',
            'id' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $ids = isset($data['id']) ? [$data['id']] : array_keys(PublicGuidanceCatalog::SOURCES);
        $needle = mb_strtolower($data['query']);
        $limit = $data['limit'] ?? 10;
        $matches = [];

        foreach ($ids as $id) {
            $source = PublicGuidanceCatalog::find($id);
            if (! $source) {
                continue;
            }

            $path = "growth/public-guidance/{$id}.txt";
            if (! Storage::disk('local')->exists($path)) {
                continue;
            }

            $text = Storage::disk('local')->get($path);
            $offset = 0;
            while (($pos = mb_stripos($text, $needle, $offset)) !== false) {
                $matches[] = [
                    'id' => $id,
                    'title' => $source['title'],
                    'snippet' => $this->snippet($text, $pos),
                    'resource_uri' => "growth://guidance/{$id}",
                ];
                if (count($matches) >= $limit) {
                    break 2;
                }
                $offset = $pos + mb_strlen($needle);
            }
        }

        return Response::structured([
            'query' => $data['query'],
            'count' => count($matches),
            'matches' => $matches,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Search term')->required(),
            'id' => $schema->string()->description('Optional guidance id to search'),
            'limit' => $schema->integer()->description('Maximum snippets (1-20, default 10)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
            'count' => $schema->integer()->required(),
            'matches' => $schema->array()->required(),
        ];
    }

    private function snippet(string $text, int $position): string
    {
        $start = max(0, $position - 180);
        $snippet = mb_substr($text, $start, 420);
        $snippet = preg_replace('/\s+/', ' ', $snippet) ?? $snippet;

        return trim($snippet);
    }
}
