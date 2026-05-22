<?php

namespace App\Mcp\Tools\Dashboard;

use App\Growth\Digest\WhatNeedsMeDigest;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Summarise what is open and waiting on you in a project — your queue. Returns, grouped by kind: approved-but-unimplemented change requests, lint errors, reviews awaiting your sign-off, blocked work items, and open decision requests routed to a role you hold. Lint errors on subjects with no owning role are listed separately as unowned.')]
class SummarizeMyQueue extends Tool
{
    public function __construct(private readonly WhatNeedsMeDigest $digest) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $project = Project::findOrFail($data['project_id']);

        return Response::structured($this->digest->for($project, auth()->user()));
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
            'change_requests' => $schema->array()->description('Approved change requests requested by a role you hold')->required(),
            'reviews' => $schema->array()->description('Open reviews awaiting your sign-off')->required(),
            'blocked_work_items' => $schema->array()->description('Blocked work items you are responsible for, including consult_with roles to involve before decisions when present')->required(),
            'decision_requests' => $schema->array()->description('Open decision requests routed to a role you hold')->required(),
            'lint_findings' => $schema->array()->description('Lint errors on artifacts owned by a role you hold')->required(),
            'unowned_lint_findings' => $schema->array()->description('Lint errors on artifacts with no owning role')->required(),
            'total' => $schema->integer()->description('Count across the five routed kinds, excluding unowned lint errors')->required(),
        ];
    }
}
