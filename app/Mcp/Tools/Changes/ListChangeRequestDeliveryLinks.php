<?php

namespace App\Mcp\Tools\Changes;

use App\Models\ChangeRequest;
use App\Models\ChangeRequestDeliveryLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List delivery links for one change request or all change requests in a project.')]
class ListChangeRequestDeliveryLinks extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required_without:change_request_id|nullable|string|owned_project',
            'change_request_id' => 'nullable|string|owned_change_request',
            'type' => 'nullable|in:'.implode(',', ChangeRequestDeliveryLink::TYPES),
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = ChangeRequestDeliveryLink::query()->with('changeRequest:id,project_id,title');
        if (isset($data['change_request_id'])) {
            $query->where('change_request_id', $data['change_request_id']);
        } else {
            $query->whereIn('change_request_id', ChangeRequest::where('project_id', $data['project_id'])->pluck('id'));
        }
        if (isset($data['type'])) {
            $query->where('type', $data['type']);
        }

        $total = (clone $query)->count();

        $rows = $query->orderBy('type')->orderBy('ref')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (ChangeRequestDeliveryLink $link): array => [
                'id' => $link->id,
                'change_request_id' => $link->change_request_id,
                'change_request' => $link->changeRequest?->title,
                'type' => $link->type,
                'ref' => $link->ref,
                'url' => $link->url,
                'description' => $link->description,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID. Required unless change_request_id is provided.'),
            'change_request_id' => $schema->string()->description('Change request ULID'),
            'type' => $schema->string()->description('Filter by delivery link type')->enum(ChangeRequestDeliveryLink::TYPES),
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
