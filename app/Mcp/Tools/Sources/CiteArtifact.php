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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Cite a source from a project artifact (requirement, concern, design_view, custom_viewpoint, test_case, anomaly). Idempotent on (source_id, citable_type, citable_id, locator) — citing the same source/artifact/locator returns the existing citation row.')]
class CiteArtifact extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $types = array_keys(AppServiceProvider::MORPH_MAP);

        $data = $request->validate([
            'source_id' => 'required|string|owned_source',
            'citable_type' => 'required|string|in:'.implode(',', $types),
            'citable_id' => 'required|string',
            'quote' => 'nullable|string',
            'locator' => 'nullable|string|max:255',
        ]);

        $ownedRule = 'owned_'.$data['citable_type'];
        $request->validate([
            'citable_id' => 'required|string|'.$ownedRule,
        ]);

        $citation = Citation::firstOrCreate(
            [
                'source_id' => $data['source_id'],
                'citable_type' => $data['citable_type'],
                'citable_id' => $data['citable_id'],
                'locator' => $data['locator'] ?? null,
            ],
            [
                'quote' => $data['quote'] ?? null,
            ],
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
            'source_id' => $schema->string()
                ->description('Source ULID this citation points back to')
                ->required(),
            'citable_type' => $schema->string()
                ->description('Artifact kind being cited')
                ->enum(array_keys(AppServiceProvider::MORPH_MAP))
                ->required(),
            'citable_id' => $schema->string()
                ->description('ULID of the artifact being cited')
                ->required(),
            'quote' => $schema->string()
                ->description('Optional verbatim excerpt from the source'),
            'locator' => $schema->string()
                ->description('Where inside the source, e.g. "section 3.2.1", "Figma frame 12", or "p.4"'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'source_id' => $schema->string()->required(),
            'citable_type' => $schema->string()->required(),
            'citable_id' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
