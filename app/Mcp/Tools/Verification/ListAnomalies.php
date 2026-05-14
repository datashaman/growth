<?php

namespace App\Mcp\Tools\Verification;

use App\Models\Anomaly;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List anomalies for a project.')]
class ListAnomalies extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'severity' => 'nullable|string|in:'.implode(',', Anomaly::SEVERITIES),
            'status' => 'nullable|string|in:'.implode(',', Anomaly::STATUSES),
            'q' => 'nullable|string|max:255',
        ]);

        $query = Anomaly::query()->where('project_id', $data['project_id']);
        foreach (['severity', 'status'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (isset($data['q'])) {
            $query->where('summary', 'like', '%'.$data['q'].'%');
        }

        return Response::structured([
            'results' => $query->orderBy('status')->orderBy('severity')->orderBy('summary')->get()->map(fn ($anomaly) => [
                'id' => $anomaly->id,
                'severity' => $anomaly->severity,
                'status' => $anomaly->status,
                'summary' => $anomaly->summary,
                'environment' => $anomaly->environment,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'severity' => $schema->string()->description('Filter by severity')->enum(Anomaly::SEVERITIES),
            'status' => $schema->string()->description('Filter by status')->enum(Anomaly::STATUSES),
            'q' => $schema->string()->description('Substring match on summary'),
        ];
    }
}
