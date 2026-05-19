<?php

namespace App\Mcp\Tools\Feedback;

use App\Models\ToolFeedback;
use App\Support\AgentContext;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Submit qualitative feedback about using the Growth MCP tools — a difficulty, a bug, a suggestion, or a missing capability. This is the qualitative counterpart to the auto-recorded tool-invocation log. Call `search-feedback` first: if an equivalent entry already exists, do not submit a duplicate. Feedback starts as `new`; it is then triaged and resolved via the feedback transition tools (triage-feedback, resolve-feedback, reopen-feedback) or the webapp.')]
class SendFeedback extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'category' => 'required|string|in:'.implode(',', ToolFeedback::CATEGORIES),
            'summary' => 'required|string|max:255',
            'body' => 'required|string',
            'tool_name' => 'nullable|string|max:120',
            'project_id' => 'nullable|string|owned_project',
            'status' => 'prohibited',
        ], [
            'status.prohibited' => 'Feedback status is not set here. Feedback starts as `new`; move it with the feedback transition tools (triage-feedback, resolve-feedback, reopen-feedback).',
        ]);

        $feedback = ToolFeedback::create([
            'workspace_id' => app(WorkspaceContext::class)->requireId(),
            'user_id' => auth()->user()?->getKey(),
            'agent_id' => app(AgentContext::class)->id(),
            'project_id' => $data['project_id'] ?? null,
            'category' => $data['category'],
            'status' => 'new',
            'tool_name' => $data['tool_name'] ?? null,
            'summary' => $data['summary'],
            'body' => $data['body'],
        ]);

        return Response::structured([
            'id' => $feedback->id,
            'category' => $feedback->category,
            'status' => $feedback->status,
            'created' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->description('Feedback category')->enum(ToolFeedback::CATEGORIES)->required(),
            'summary' => $schema->string()->description('One-line summary, max 255 chars')->required(),
            'body' => $schema->string()->description('Full description of the difficulty, bug, suggestion, or missing capability')->required(),
            'tool_name' => $schema->string()->description('The MCP tool this feedback is about, e.g. upsert-requirements'),
            'project_id' => $schema->string()->description('Optional project ULID this feedback relates to'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'category' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
