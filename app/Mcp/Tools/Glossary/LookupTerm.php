<?php

namespace App\Mcp\Tools\Glossary;

use App\Growth\Glossary\GlossaryParser;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Look up terms in the Growth domain glossary.')]
class LookupTerm extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'query' => 'required|string|min:1|max:120',
            'mode' => 'nullable|in:exact,prefix,contains',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $mode = $data['mode'] ?? 'contains';
        $limit = $data['limit'] ?? 10;
        $needle = mb_strtolower($data['query']);

        // The glossary ships with the application as a committed resource, so a
        // lookup never errors. The absent-file branch is a defensive fallback:
        // an empty result, never a leaked filesystem path.
        $path = resource_path('glossary/glossary-extract.txt');
        if (! File::exists($path)) {
            return Response::structured([
                'query' => $data['query'],
                'mode' => $mode,
                'count' => 0,
                'matches' => [],
            ]);
        }

        $entries = Cache::remember(
            'growth:glossary:glossary-extract',
            now()->addHour(),
            fn () => (new GlossaryParser)->parse(File::get($path)),
        );

        $matches = [];
        foreach ($entries as $entry) {
            $term = mb_strtolower($entry['term']);
            $hit = match ($mode) {
                'exact' => $term === $needle,
                'prefix' => str_starts_with($term, $needle),
                default => str_contains($term, $needle),
            };

            if ($hit) {
                $matches[] = $entry;
                if (count($matches) >= $limit) {
                    break;
                }
            }
        }

        return Response::structured([
            'query' => $data['query'],
            'mode' => $mode,
            'count' => count($matches),
            'matches' => $matches,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Term or partial term to look up, case-insensitive')->required(),
            'mode' => $schema->string()->description('Match mode: exact, prefix, or contains'),
            'limit' => $schema->integer()->description('Maximum matches to return, 1-50; default 10'),
        ];
    }
}
