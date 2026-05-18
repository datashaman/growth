<?php

namespace App\Mcp\Tools\Common;

use App\Models\User;
use App\Notifications\DirectMessage;
use App\Notifications\WorkspaceNotifier;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description("Send a notification to another member of the active workspace — a free-text message that lands in their bell inbox. Call list-users to find the recipient's user_id.")]
class SendNotification extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $sender = auth()->user();

        if (! $sender instanceof User) {
            return new ResponseFactory(Response::error('Sending a notification needs an authenticated user.'));
        }

        $workspaceId = app(WorkspaceContext::class)->requireId();

        $data = $request->validate([
            'user_id' => 'required|integer|exists:workspace_memberships,user_id,workspace_id,'.$workspaceId,
            'message' => 'required|string|max:2000',
        ], [
            'user_id.exists' => 'The recipient must be a member of the active workspace.',
        ]);

        $recipient = User::findOrFail($data['user_id']);

        app(WorkspaceNotifier::class)->notifyUser($recipient, new DirectMessage($sender, $data['message']));

        return Response::structured([
            'user_id' => $recipient->getKey(),
            'sent' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()
                ->description('Integer id of the recipient — a member of the active workspace. From list-users or who-am-i.')
                ->required(),
            'message' => $schema->string()
                ->description('The message to deliver, max 2000 characters.')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->required(),
            'sent' => $schema->boolean()->required(),
        ];
    }
}
