<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Project;
use App\Models\Requirement;
use App\Models\Mockup;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List spec mockup coverage across a whole project. Filter by owner type, work-item status, whether the owner needs mockups, or missing mockup coverage so agents can refresh UI mockups without calling list-mockups once per owner.')]
class ListProjectMockups extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'owner_type' => 'nullable|string|in:work_item,requirement',
            'work_item_status' => 'nullable|string|in:'.implode(',', WorkItem::STATUSES),
            'needs_mockups' => 'nullable|boolean',
            'missing_mockups' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        Project::findOrFail($data['project_id']);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;
        $rows = collect();

        if (($data['owner_type'] ?? null) !== 'requirement') {
            $rows = $rows->merge($this->workItemRows($data));
        }

        if (($data['owner_type'] ?? null) !== 'work_item' && ! isset($data['work_item_status'])) {
            $rows = $rows->merge($this->requirementRows($data));
        }

        $rows = $rows->sortBy([
            ['owner_type', 'asc'],
            ['reference', 'asc'],
            ['name', 'asc'],
        ])->values();

        return Response::structured([
            'project_id' => $data['project_id'],
            'total' => $rows->count(),
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->slice($offset, $limit)->values()->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'owner_type' => $schema->string()->description('Filter to work_item or requirement owners')->enum(['work_item', 'requirement']),
            'work_item_status' => $schema->string()->description('Filter work-item owners by status; requirement owners are omitted when this filter is present')->enum(WorkItem::STATUSES),
            'needs_mockups' => $schema->boolean()->description('Filter by owners expected to have mockups. Work items use needs_mockups; requirements use renders_ui.'),
            'missing_mockups' => $schema->boolean()->description('When true, only owners with no mockups. When false, only owners with one or more mockups.'),
            'limit' => $schema->integer()->description('Page size (1-200, default 50)'),
            'offset' => $schema->integer()->description('Offset for pagination (default 0)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'total' => $schema->integer()->required(),
            'limit' => $schema->integer()->required(),
            'offset' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, array<string, mixed>>
     */
    private function workItemRows(array $data): Collection
    {
        $query = WorkItem::query()
            ->where('project_id', $data['project_id'])
            ->with(['mockups' => fn ($query) => $query->orderBy('name')])
            ->withCount('mockups');

        if (isset($data['work_item_status'])) {
            $query->where('status', $data['work_item_status']);
        }
        if (array_key_exists('needs_mockups', $data)) {
            $query->where('needs_mockups', (bool) $data['needs_mockups']);
        }
        if (array_key_exists('missing_mockups', $data)) {
            $query->{(bool) $data['missing_mockups'] ? 'doesntHave' : 'has'}('mockups');
        }

        return $query->get(['id', 'number', 'kind', 'name', 'status', 'needs_mockups'])
            ->map(fn (WorkItem $item): array => [
                'owner_type' => 'work_item',
                'owner_id' => $item->id,
                'reference' => $item->reference(),
                'name' => $item->name,
                'kind' => $item->kind,
                'status' => $item->status,
                'needs_mockups' => $item->needs_mockups,
                'mockups_count' => $item->mockups_count,
                'missing_mockups' => $item->mockups_count === 0,
                'mockups' => $this->mockupRows($item->mockups),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, array<string, mixed>>
     */
    private function requirementRows(array $data): Collection
    {
        $query = Requirement::query()
            ->where('project_id', $data['project_id'])
            ->with(['mockups' => fn ($query) => $query->orderBy('name')])
            ->withCount('mockups');

        if (array_key_exists('needs_mockups', $data)) {
            $query->where('renders_ui', (bool) $data['needs_mockups']);
        }
        if (array_key_exists('missing_mockups', $data)) {
            $query->{(bool) $data['missing_mockups'] ? 'doesntHave' : 'has'}('mockups');
        }

        return $query->get(['id', 'number', 'doc', 'type', 'text', 'priority', 'renders_ui'])
            ->map(fn (Requirement $requirement): array => [
                'owner_type' => 'requirement',
                'owner_id' => $requirement->id,
                'reference' => $requirement->reference(),
                'name' => $requirement->text,
                'kind' => $requirement->type,
                'status' => null,
                'needs_mockups' => $requirement->renders_ui,
                'mockups_count' => $requirement->mockups_count,
                'missing_mockups' => $requirement->mockups_count === 0,
                'mockups' => $this->mockupRows($requirement->mockups),
            ]);
    }

    /**
     * @param  Collection<int, Mockup>  $mockups
     * @return list<array{id:string,name:string,updated_at:?string}>
     */
    private function mockupRows(Collection $mockups): array
    {
        return $mockups->map(fn (Mockup $mockup): array => [
            'id' => $mockup->id,
            'name' => $mockup->name,
            'updated_at' => $mockup->updated_at?->toIso8601String(),
        ])->values()->all();
    }
}
