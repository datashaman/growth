<?php

namespace App\Mcp\Tools\Changes;

use App\Models\ChangeApprovalEvent;
use App\Models\ChangeRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List append-only approval/decision events for change requests in a project or for a specific change request.')]
class ListChangeApprovalEvents extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required_without:change_request_id|nullable|string|owned_project',
            'change_request_id' => 'nullable|string|owned_change_request',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = ChangeApprovalEvent::query()
            ->with(['changeRequest:id,project_id,title,category,status', 'recordedBy:id,name,email']);

        if (isset($data['change_request_id'])) {
            $query->where('change_request_id', $data['change_request_id']);
        } else {
            $query->whereIn('change_request_id', ChangeRequest::where('project_id', $data['project_id'])->pluck('id'));
        }

        $total = (clone $query)->count();

        $rows = $query->orderByDesc('recorded_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (ChangeApprovalEvent $event): array => [
                'id' => $event->id,
                'change_request_id' => $event->change_request_id,
                'change_request' => $event->changeRequest?->title,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'from_decision' => $event->from_decision,
                'to_decision' => $event->to_decision,
                'rationale' => $event->rationale,
                'recorded_by_user_id' => $event->recorded_by_user_id,
                'recorded_by' => $event->recordedBy?->name,
                'recorded_at' => $event->recorded_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID. Required unless change_request_id is provided.'),
            'change_request_id' => $schema->string()->description('Change request ULID'),
            'limit' => $schema->integer()->description('Page size (1-200, default 50)'),
            'offset' => $schema->integer()->description('Offset for pagination (default 0)'),
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
