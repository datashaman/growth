<?php

namespace App\Mcp\Tools\Reviews;

use App\Growth\Transitions\DispositionFinding as DispositionFindingTransition;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\ReviewFinding;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Disposition a review finding: move it from open to dispositioned. Rejects any other source status with a clear message. Records a status transition with the acting user and timestamp.')]
class DispositionFinding extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'review_finding_id' => 'required|string|owned_review_finding',
            'reason' => 'nullable|string|max:1000',
        ]);

        $finding = ReviewFinding::findOrFail($data['review_finding_id']);

        try {
            $transition = (new DispositionFindingTransition)->apply($finding, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'review_finding_id' => $finding->id,
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'transition_id' => $transition->id,
            'transitioned_at' => $transition->transitioned_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'review_finding_id' => $schema->string()->description('ReviewFinding ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the transition'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'review_finding_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'transition_id' => $schema->string()->required(),
            'transitioned_at' => $schema->string()->required(),
        ];
    }
}
