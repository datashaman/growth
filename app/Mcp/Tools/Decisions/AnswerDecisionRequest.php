<?php

namespace App\Mcp\Tools\Decisions;

use App\Growth\Transitions\AnswerDecisionRequest as AnswerDecisionRequestTransition;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\DecisionRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Answer an open decision request by choosing one of its options and giving a rationale. Only a user assigned to the target role may answer. Answering notifies the requester and closes the request.')]
class AnswerDecisionRequest extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'decision_request_id' => 'required|string|owned_decision_request',
            'option_id' => 'required|string',
            'rationale' => 'required|string|max:2000',
        ]);

        $decisionRequest = DecisionRequest::findOrFail($data['decision_request_id']);

        $option = $decisionRequest->options()->find($data['option_id']);

        if ($option === null) {
            return new ResponseFactory(Response::error('That option does not belong to this decision request.'));
        }

        $actor = auth()->user();

        // Routing is to a role; only someone holding that role may answer.
        $isAssignee = $actor !== null
            && $decisionRequest->targetRole()->first()?->users()->whereKey($actor->getKey())->exists();

        if (! $isAssignee) {
            return new ResponseFactory(Response::error('Only a user assigned to the target role may answer this decision request.'));
        }

        try {
            (new AnswerDecisionRequestTransition($option, $data['rationale'], $actor))
                ->apply($decisionRequest, $actor, $data['rationale']);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'id' => $decisionRequest->id,
            'status' => $decisionRequest->status,
            'chosen_option_id' => $decisionRequest->chosen_option_id,
            'answered' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'decision_request_id' => $schema->string()->description('DecisionRequest ULID')->required(),
            'option_id' => $schema->string()->description('The chosen option\'s ULID')->required(),
            'rationale' => $schema->string()->description('Why this option was chosen')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'chosen_option_id' => $schema->string()->required(),
            'answered' => $schema->boolean()->required(),
        ];
    }
}
