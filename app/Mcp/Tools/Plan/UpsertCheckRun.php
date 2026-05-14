<?php

namespace App\Mcp\Tools\Plan;

use App\Models\CheckRunEvidence;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update CI/check-run evidence for a work-item delivery link.')]
class UpsertCheckRun extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_check_run_evidence',
            'work_item_delivery_link_id' => 'required|string|owned_work_item_delivery_link',
            'provider' => 'nullable|string|max:120',
            'name' => 'required|string|max:255',
            'run_ref' => 'nullable|string|max:255',
            'status' => 'nullable|in:'.implode(',', CheckRunEvidence::STATUSES),
            'conclusion' => 'nullable|in:'.implode(',', CheckRunEvidence::CONCLUSIONS),
            'url' => 'nullable|url|max:2048',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $check = $id
            ? tap(CheckRunEvidence::findOrFail($id))->update($data)
            : CheckRunEvidence::updateOrCreate(
                [
                    'work_item_delivery_link_id' => $data['work_item_delivery_link_id'],
                    'provider' => $data['provider'] ?? null,
                    'name' => $data['name'],
                ],
                $data,
            );

        return Response::structured([
            'id' => $check->id,
            'work_item_delivery_link_id' => $check->work_item_delivery_link_id,
            'provider' => $check->provider,
            'name' => $check->name,
            'run_ref' => $check->run_ref,
            'status' => $check->status,
            'conclusion' => $check->conclusion,
            'url' => $check->url,
            'created' => $check->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing check-run evidence ULID. Omit to create.'),
            'work_item_delivery_link_id' => $schema->string()->description('Delivery link ULID')->required(),
            'provider' => $schema->string()->description('CI provider, e.g. github-actions'),
            'name' => $schema->string()->description('Check or workflow name')->required(),
            'run_ref' => $schema->string()->description('Provider run id, job id, or check suite ref'),
            'status' => $schema->string()->description('Check lifecycle status')->enum(CheckRunEvidence::STATUSES),
            'conclusion' => $schema->string()->description('Completed check conclusion')->enum(CheckRunEvidence::CONCLUSIONS),
            'url' => $schema->string()->description('Optional URL to the run/check result'),
            'started_at' => $schema->string()->description('Check start timestamp'),
            'completed_at' => $schema->string()->description('Check completion timestamp'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'work_item_delivery_link_id' => $schema->string()->required(),
            'provider' => $schema->string(),
            'name' => $schema->string()->required(),
            'run_ref' => $schema->string(),
            'status' => $schema->string()->required(),
            'conclusion' => $schema->string(),
            'url' => $schema->string(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
