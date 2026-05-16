<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\RollBackDeployment as RollBackDeploymentTransition;
use App\Models\Deployment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Roll back a finished deployment: move it from succeeded or failed to rolled_back. Rejects any other source status with a clear message. Records a status transition with the acting user and timestamp.')]
class RollBackDeployment extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'deployment_id' => 'required|string|owned_deployment',
            'reason' => 'nullable|string|max:1000',
        ]);

        $deployment = Deployment::findOrFail($data['deployment_id']);

        try {
            $transition = (new RollBackDeploymentTransition)->apply($deployment, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'deployment_id' => $deployment->id,
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'transition_id' => $transition->id,
            'transitioned_at' => $transition->transitioned_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'deployment_id' => $schema->string()->description('Deployment ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the transition'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'deployment_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'transition_id' => $schema->string()->required(),
            'transitioned_at' => $schema->string()->required(),
        ];
    }
}
