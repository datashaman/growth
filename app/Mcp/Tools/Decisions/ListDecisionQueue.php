<?php

namespace App\Mcp\Tools\Decisions;

use App\Models\DecisionRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the decision requests routed to you — your queue. Defaults to open requests targeting any role you are assigned to; pass role_id to inspect one specific role\'s queue, or status to see answered, expired, or cancelled requests.')]
class ListDecisionQueue extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'role_id' => 'nullable|string|owned_role',
            'status' => 'nullable|string|in:'.implode(',', DecisionRequest::STATUSES),
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $roleIds = isset($data['role_id'])
            ? [$data['role_id']]
            : auth()->user()?->roles()->pluck('roles.id')->all() ?? [];

        $status = $data['status'] ?? 'open';
        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = DecisionRequest::query()
            ->whereIn('target_role_id', $roleIds)
            ->where('status', $status);

        $total = (clone $query)->count();

        $requests = $query
            ->with(['options', 'requester', 'targetRole'])
            ->orderBy('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'status' => $status,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'count' => $requests->count(),
            'decision_requests' => $requests->map(fn (DecisionRequest $decisionRequest): array => [
                'id' => $decisionRequest->id,
                'question' => $decisionRequest->question,
                'status' => $decisionRequest->status,
                'target_role_id' => $decisionRequest->target_role_id,
                'target_role' => $decisionRequest->targetRole?->name,
                'requester' => $decisionRequest->requester?->name,
                'deadline' => $decisionRequest->deadline?->toIso8601String(),
                'options' => $decisionRequest->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'label' => $option->label,
                ])->all(),
                'created_at' => $decisionRequest->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'role_id' => $schema->string()->description('Optional Role ULID; defaults to every role you are assigned to'),
            'status' => $schema->string()->description('Lifecycle status to list (default open)')->enum(DecisionRequest::STATUSES),
            'limit' => $schema->integer()->description('Page size (1-200, default 50)'),
            'offset' => $schema->integer()->description('Offset for pagination (default 0)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->required(),
            'total' => $schema->integer()->required(),
            'limit' => $schema->integer()->required(),
            'offset' => $schema->integer()->required(),
            'count' => $schema->integer()->required(),
            'decision_requests' => $schema->array()->description('The queued decision requests, oldest first')->required(),
        ];
    }
}
