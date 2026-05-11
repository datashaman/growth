<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Plan\ScheduleHealthSummarizer;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Summarize schedule health and dependency risks from milestones, work-item due dates, and dependency dates/statuses.')]
class SummarizeScheduleHealth extends Tool
{
    public function __construct(private readonly ScheduleHealthSummarizer $summarizer) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        return Response::structured($this->summarizer->summarize(Project::findOrFail($data['project_id'])));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'summary' => $schema->object()->required(),
            'findings' => $schema->array()->required(),
        ];
    }
}
