<?php

namespace App\Mcp\Tools\Changes;

use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\RejectChangeRequest as RejectChangeRequestTransition;
use App\Models\ChangeRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Reject a change request under review: move it from under_review to rejected and record the rejected decision. Rejects any other source status with a clear message. Records a change approval event with the acting user and timestamp.')]
class RejectChangeRequest extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'change_request_id' => 'required|string|owned_change_request',
            'reason' => 'nullable|string|max:1000',
        ]);

        $changeRequest = ChangeRequest::findOrFail($data['change_request_id']);

        try {
            $event = (new RejectChangeRequestTransition)->apply($changeRequest, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'change_request_id' => $changeRequest->id,
            'reference' => $changeRequest->reference(),
            'from_status' => $event->from_status,
            'to_status' => $event->to_status,
            'decision' => $changeRequest->decision,
            'approval_event_id' => $event->id,
            'recorded_at' => $event->recorded_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'change_request_id' => $schema->string()->description('ChangeRequest ULID')->required(),
            'reason' => $schema->string()->description('Decision rationale recorded with the approval event'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'change_request_id' => $schema->string()->required(),
            'reference' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'decision' => $schema->string(),
            'approval_event_id' => $schema->string()->required(),
            'recorded_at' => $schema->string()->required(),
        ];
    }
}
