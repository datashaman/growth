<?php

namespace App\Mcp\Tools\Glossary;

use App\Growth\Glossary\GlossaryParser;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Look up terms in the approved internal project glossary.')]
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

        $path = 'growth/glossary/glossary-extract.txt';
        if (! Storage::disk('local')->exists($path)) {
            return new ResponseFactory(Response::error('No approved internal glossary extract found at storage/app/growth/glossary/glossary-extract.txt.'));
        }

        $entries = Cache::remember(
            'growth:glossary:glossary-extract',
            now()->addHour(),
            fn () => (new GlossaryParser)->parse(Storage::disk('local')->get($path)),
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
