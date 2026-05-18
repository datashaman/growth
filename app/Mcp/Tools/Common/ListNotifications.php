<?php

namespace App\Mcp\Tools\Common;

use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the notifications addressed to you in the active workspace, newest first. Each entry records who sent it and the role they were acting in. Use mark-notification-read to clear them.')]
class ListNotifications extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'unread_only' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 25;
        $offset = $data['offset'] ?? 0;
        $workspaceId = app(WorkspaceContext::class)->requireId();

        $query = auth()->user()->notifications()
            ->where('data->workspace_id', $workspaceId);

        if ($data['unread_only'] ?? false) {
            $query->whereNull('read_at');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (DatabaseNotification $row): array => [
                'id' => $row->id,
                'thread_id' => $row->data['thread_id'] ?? $row->id,
                'event' => $row->data['event'] ?? null,
                'title' => $row->data['title'] ?? null,
                'body' => $row->data['body'] ?? null,
                'url' => $row->data['url'] ?? null,
                'sender' => $row->data['sender'] ?? null,
                'acting_role' => $row->data['acting_role'] ?? null,
                'read' => $row->read_at !== null,
                'created_at' => $row->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'unread_only' => $schema->boolean()->description('Return only notifications you have not read yet. Default false.'),
            'limit' => $schema->integer()->description('Page size, max 100. Default 25.'),
            'offset' => $schema->integer()->description('Pagination offset. Default 0.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'total' => $schema->integer()->required(),
            'limit' => $schema->integer()->required(),
            'offset' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
