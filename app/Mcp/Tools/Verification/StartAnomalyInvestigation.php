<?php

namespace App\Mcp\Tools\Verification;

use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\StartAnomalyInvestigation as StartAnomalyInvestigationTransition;
use App\Models\Anomaly;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Start investigating an anomaly: move it from open to investigating. Rejects any other source status with a clear message. Records a status transition with the acting user and timestamp.')]
class StartAnomalyInvestigation extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'anomaly_id' => 'required|string|owned_anomaly',
            'reason' => 'nullable|string|max:1000',
        ]);

        $anomaly = Anomaly::findOrFail($data['anomaly_id']);

        try {
            $transition = (new StartAnomalyInvestigationTransition)->apply($anomaly, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'anomaly_id' => $anomaly->id,
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'transition_id' => $transition->id,
            'transitioned_at' => $transition->transitioned_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'anomaly_id' => $schema->string()->description('Anomaly ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the transition'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'anomaly_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'transition_id' => $schema->string()->required(),
            'transitioned_at' => $schema->string()->required(),
        ];
    }
}
