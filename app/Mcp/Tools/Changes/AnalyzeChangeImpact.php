<?php

namespace App\Mcp\Tools\Changes;

use App\Growth\Changes\ChangeImpactAnalyzer;
use App\Models\ChangeRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Analyze a change request by expanding impacted artifacts into nearby traceability context and unresolved impact-analysis items.')]
class AnalyzeChangeImpact extends Tool
{
    public function __construct(private readonly ChangeImpactAnalyzer $analyzer) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_change_request',
            'depth' => 'nullable|integer|min:1|max:4',
        ]);

        return Response::structured($this->analyzer->analyze(
            ChangeRequest::findOrFail($data['id']),
            $data['depth'] ?? 2,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Change request ULID')->required(),
            'depth' => $schema->integer()->description('Trace depth per impact, 1-4, default 2'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'change_request_id' => $schema->string()->required(),
            'title' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'decision' => $schema->string(),
            'summary' => $schema->object()->required(),
            'impacts' => $schema->array()->required(),
        ];
    }
}
