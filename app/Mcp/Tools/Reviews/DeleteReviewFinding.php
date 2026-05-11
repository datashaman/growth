<?php

namespace App\Mcp\Tools\Reviews;

use App\Models\ReviewFinding;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a review finding. Citations attached to the finding are removed.')]
class DeleteReviewFinding extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_review_finding',
        ]);

        $finding = ReviewFinding::findOrFail($data['id']);
        $finding->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Review finding ULID')->required(),
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
