<?php

namespace App\Mcp\Tools;

use App\Models\DesignView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List architecture views for a project. For the elements and concerns attached to a specific view, use `trace-query` with the view id.')]
class ListArchitectureViews extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'viewpoint' => 'nullable|string|max:100',
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;
        $query = DesignView::query()->where('project_id', $data['project_id'])->withCount(['elements', 'concerns']);

        if (isset($data['viewpoint'])) {
            $query->where('viewpoint', $data['viewpoint']);
        }
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('viewpoint')->orderBy('name')->limit($limit)->offset($offset)->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($view) => [
                'id' => $view->id,
                'viewpoint' => $view->viewpoint,
                'name' => $view->name,
                'description' => $view->description,
                'elements_count' => $view->elements_count,
                'concerns_count' => $view->concerns_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'viewpoint' => $schema->string()->description('Filter by viewpoint name'),
            'q' => $schema->string()->description('Substring match on view name'),
            'limit' => $schema->integer()->description('Page size, default 50'),
            'offset' => $schema->integer()->description('Pagination offset, default 0'),
        ];
    }
}
