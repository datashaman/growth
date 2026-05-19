<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\MarkRiskRealized as MarkRiskRealizedTransition;
use App\Models\Risk;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Mark a risk as realized: move it to realized from any active state. Rejects closed and already-realized risks with a clear message. Records a status transition with the acting user and timestamp.')]
class MarkRiskRealized extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'risk_id' => 'required|string|owned_risk',
            'reason' => 'nullable|string|max:1000',
        ]);

        $risk = Risk::findOrFail($data['risk_id']);

        try {
            $transition = (new MarkRiskRealizedTransition)->apply($risk, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'risk_id' => $risk->id,
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'transition_id' => $transition->id,
            'transitioned_at' => $transition->transitioned_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'risk_id' => $schema->string()->description('Risk ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the transition'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'risk_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'transition_id' => $schema->string()->required(),
            'transitioned_at' => $schema->string()->required(),
        ];
    }
}
