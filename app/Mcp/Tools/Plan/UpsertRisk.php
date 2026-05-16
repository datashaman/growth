<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Risk;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a project risk register item (risk management). Use owner_role_id for the role accountable for mitigation. New risks start as `identified`; status is not set here — it moves only through the assess-risk, start-risk-mitigation, mark-risk-mitigated, accept-risk, mark-risk-realized, and close-risk transitions.')]
class UpsertRisk extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_risk',
            'project_id' => 'required|string|owned_project',
            'owner_role_id' => 'nullable|string|owned_role',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:'.implode(',', Risk::CATEGORIES),
            'probability' => 'required|in:'.implode(',', Risk::EXPOSURES),
            'impact' => 'required|in:'.implode(',', Risk::EXPOSURES),
            'status' => 'prohibited',
            'mitigation_plan' => 'nullable|string',
        ], [
            'status.prohibited' => 'Risk status is not set here. Use the assess-risk, start-risk-mitigation, mark-risk-mitigated, accept-risk, mark-risk-realized, and close-risk tools to move status through validated transitions.',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $risk = $id
            ? tap(Risk::findOrFail($id))->update($data)
            : Risk::create($data + ['status' => 'identified']);

        return Response::structured([
            'id' => $risk->id,
            'project_id' => $risk->project_id,
            'title' => $risk->title,
            'category' => $risk->category,
            'probability' => $risk->probability,
            'impact' => $risk->impact,
            'status' => $risk->status,
            'created' => $risk->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing risk ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'owner_role_id' => $schema->string()->description('Role ULID accountable for mitigation'),
            'title' => $schema->string()->description('Short risk title')->required(),
            'description' => $schema->string()->description('Risk cause/event/consequence narrative'),
            'category' => $schema->string()->description('Risk category')->enum(Risk::CATEGORIES)->required(),
            'probability' => $schema->string()->description('Probability rating')->enum(Risk::EXPOSURES)->required(),
            'impact' => $schema->string()->description('Impact rating')->enum(Risk::EXPOSURES)->required(),
            'mitigation_plan' => $schema->string()->description('Avoid/reduce/transfer/accept response plan'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'title' => $schema->string()->required(),
            'category' => $schema->string()->required(),
            'probability' => $schema->string()->required(),
            'impact' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
