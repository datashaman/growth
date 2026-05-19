<?php

namespace App\Mcp\Tools\Assurance;

use App\Growth\Alignment\AlignmentText;
use App\Growth\Assurance\ReadinessGateEvaluator;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Evaluate lifecycle readiness gates across requirements, architecture, verification, planning, review, change control, and implementation.')]
class EvaluateReadinessGates extends Tool
{
    public function __construct(private readonly ReadinessGateEvaluator $evaluator) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        return Response::structured(AlignmentText::sanitizeArray(
            $this->evaluator->evaluate(Project::findOrFail($data['project_id'])),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
        ];
    }
}
