<?php

namespace App\Mcp\Tools\Common;

use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Mark your notifications read. Pass a notification_id to clear one; omit it to clear every unread notification in the active workspace. Already-read notifications are left untouched.')]
class MarkNotificationRead extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'notification_id' => 'nullable|string',
        ]);

        $workspaceId = app(WorkspaceContext::class)->requireId();

        $query = auth()->user()->notifications()
            ->where('data->workspace_id', $workspaceId)
            ->whereNull('read_at');

        if (isset($data['notification_id'])) {
            $query->whereKey($data['notification_id']);
        }

        $marked = $query->update(['read_at' => now()]);

        return Response::structured([
            'marked' => $marked,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'notification_id' => $schema->string()->description('Notification to mark read. Omit to mark every unread notification in the active workspace.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'marked' => $schema->integer()->description('Number of notifications newly marked read')->required(),
        ];
    }
}
