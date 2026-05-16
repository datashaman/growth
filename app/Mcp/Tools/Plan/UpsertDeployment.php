<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Deployment;
use App\Models\Release;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a deployment record and optionally sync the delivery links deployed to an environment.')]
class UpsertDeployment extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_deployment',
            'project_id' => 'required|string|owned_project',
            'release_id' => 'nullable|string|owned_release',
            'environment' => 'required|string|max:120',
            'status' => 'nullable|in:'.implode(',', Deployment::STATUSES),
            'provider' => 'nullable|string|max:120',
            'external_ref' => 'nullable|string|max:255',
            'deployed_at' => 'nullable|date',
            'url' => 'nullable|url|max:2048',
            'notes' => 'nullable|string',
            'delivery_link_ids' => 'nullable|array',
            'delivery_link_ids.*' => 'string|owned_work_item_delivery_link',
        ]);

        $deliveryLinkIds = $data['delivery_link_ids'] ?? null;
        unset($data['delivery_link_ids']);

        if (isset($data['release_id'])) {
            $release = Release::findOrFail($data['release_id']);
            if ($release->project_id !== $data['project_id']) {
                throw ValidationException::withMessages([
                    'release_id' => 'Release must belong to the deployment project.',
                ]);
            }
        }

        if ($deliveryLinkIds !== null) {
            $this->assertDeliveryLinksBelongToProject($deliveryLinkIds, $data['project_id']);
        }

        $id = $data['id'] ?? null;
        unset($data['id']);

        if ($id) {
            $deployment = tap(Deployment::findOrFail($id))->update($data);
        } elseif (isset($data['provider'], $data['external_ref'])) {
            $deployment = Deployment::updateOrCreate([
                'project_id' => $data['project_id'],
                'provider' => $data['provider'],
                'external_ref' => $data['external_ref'],
            ], $data);
        } else {
            $deployment = Deployment::create($data);
        }

        if ($deliveryLinkIds !== null) {
            $deployment->deliveryLinks()->sync($deliveryLinkIds);
        }

        return Response::structured([
            'id' => $deployment->id,
            'project_id' => $deployment->project_id,
            'release_id' => $deployment->release_id,
            'environment' => $deployment->environment,
            'status' => $deployment->status,
            'deployed_at' => $deployment->deployed_at?->toIso8601String(),
            'delivery_links' => $deployment->deliveryLinks()->count(),
            'created' => $deployment->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing deployment ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'release_id' => $schema->string()->description('Release ULID deployed'),
            'environment' => $schema->string()->description('Environment name, e.g. staging or production')->required(),
            'status' => $schema->string()->description('Deployment status')->enum(Deployment::STATUSES),
            'provider' => $schema->string()->description('External provider, e.g. github-actions'),
            'external_ref' => $schema->string()->description('Provider deployment id; with provider, makes the upsert idempotent'),
            'deployed_at' => $schema->string()->description('Deployment timestamp'),
            'url' => $schema->string()->description('Deployment URL or run URL'),
            'notes' => $schema->string()->description('Deployment notes'),
            'delivery_link_ids' => $schema->array()->description('Delivery link ULIDs included in this deployment'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'release_id' => $schema->string(),
            'environment' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'deployed_at' => $schema->string(),
            'delivery_links' => $schema->integer()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }

    /**
     * @param  list<string>  $deliveryLinkIds
     */
    private function assertDeliveryLinksBelongToProject(array $deliveryLinkIds, string $projectId): void
    {
        $count = WorkItemDeliveryLink::query()
            ->whereIn('work_item_delivery_links.id', $deliveryLinkIds)
            ->join('work_items', 'work_item_delivery_links.work_item_id', '=', 'work_items.id')
            ->where('work_items.project_id', $projectId)
            ->count();

        if ($count !== count(array_unique($deliveryLinkIds))) {
            throw ValidationException::withMessages([
                'delivery_link_ids' => 'All delivery links must belong to the deployment project.',
            ]);
        }
    }
}
