<?php

namespace App\Mcp\Tools\Decisions;

use App\Growth\Transitions\CancelDecisionRequest as CancelDecisionRequestTransition;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\DecisionRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Cancel an open decision request you raised, withdrawing the question before it is answered. Only the requester may cancel.')]
class CancelDecisionRequest extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'decision_request_id' => 'required|string|owned_decision_request',
            'reason' => 'nullable|string|max:1000',
        ]);

        $decisionRequest = DecisionRequest::findOrFail($data['decision_request_id']);

        $requesterId = $decisionRequest->requester_user_id;

        if ($requesterId !== null && (string) $requesterId !== (string) auth()->id()) {
            return new ResponseFactory(Response::error('Only the user who raised this decision request may cancel it.'));
        }

        try {
            (new CancelDecisionRequestTransition)
                ->apply($decisionRequest, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'id' => $decisionRequest->id,
            'status' => $decisionRequest->status,
            'cancelled' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'decision_request_id' => $schema->string()->description('DecisionRequest ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the cancellation'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'cancelled' => $schema->boolean()->required(),
        ];
    }
}
