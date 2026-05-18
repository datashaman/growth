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

#[Description('Subscribe the calling user to a change request. While subscribed, the user is notified whenever the change request transitions status. Subscribing again is idempotent — it never creates a duplicate.')]
class SubscribeChangeRequest extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'change_request_id' => 'required|string|owned_change_request',
        ]);

        $changeRequest = ChangeRequest::findOrFail($data['change_request_id']);

        $subscription = Subscription::firstOrCreate([
            'user_id' => auth()->id(),
            'subscribable_type' => $changeRequest->getMorphClass(),
            'subscribable_id' => $changeRequest->getKey(),
        ]);

        return Response::structured([
            'change_request_id' => $changeRequest->id,
            'reference' => $changeRequest->reference(),
            'subscribed' => true,
            'already_subscribed' => ! $subscription->wasRecentlyCreated,
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
            'already_subscribed' => $schema->boolean()->description('True when a subscription already existed')->required(),
        ];
    }
}
