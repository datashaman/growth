<?php

namespace App\Mcp\Tools\Changes;

use App\Models\ChangeRequest;
use App\Models\Subscription;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Unsubscribe the calling user from a change request, stopping its status-change notifications. Unsubscribing when no subscription exists is a clean no-op.')]
class UnsubscribeChangeRequest extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'change_request_id' => 'required|string|owned_change_request',
        ]);

        $changeRequest = ChangeRequest::findOrFail($data['change_request_id']);

        $removed = Subscription::query()
            ->where('user_id', auth()->id())
            ->where('subscribable_type', $changeRequest->getMorphClass())
            ->where('subscribable_id', $changeRequest->getKey())
            ->delete();

        return Response::structured([
            'change_request_id' => $changeRequest->id,
            'reference' => $changeRequest->reference(),
            'subscribed' => false,
            'was_subscribed' => $removed > 0,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'change_request_id' => $schema->string()->description('ChangeRequest ULID')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'change_request_id' => $schema->string()->required(),
            'reference' => $schema->string()->required(),
            'subscribed' => $schema->boolean()->required(),
            'was_subscribed' => $schema->boolean()->description('True when a subscription was removed')->required(),
        ];
    }
}
