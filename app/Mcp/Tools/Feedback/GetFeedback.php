<?php

namespace App\Mcp\Tools\Feedback;

use App\Models\FeedbackComment;
use App\Models\ToolFeedback;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Fetch a single feedback entry by id, including its full body. Use this after `search-feedback`, which returns only the summary, to read the complete payload.')]
class GetFeedback extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'feedback_id' => 'required|string',
        ]);

        $feedback = ToolFeedback::query()
            ->where('workspace_id', app(WorkspaceContext::class)->requireId())
            ->find($data['feedback_id']);

        if ($feedback === null) {
            return new ResponseFactory(Response::error('No feedback with that id exists in the active workspace.'));
        }

        return Response::structured([
            'id' => $feedback->id,
            'category' => $feedback->category,
            'status' => $feedback->status,
            'tool_name' => $feedback->tool_name,
            'summary' => $feedback->summary,
            'body' => $feedback->body,
            'project_id' => $feedback->project_id,
            'agent_id' => $feedback->agent_id,
            'user_id' => $feedback->user_id,
            'created_at' => $feedback->created_at?->toIso8601String(),
            'updated_at' => $feedback->updated_at?->toIso8601String(),
            'comments' => $feedback->comments()
                ->with('author')
                ->orderBy('created_at')
                ->get()
                ->map(fn (FeedbackComment $comment): array => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'author' => $comment->author?->name,
                    'acting_surface' => $comment->acting_surface,
                    'created_at' => $comment->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feedback_id' => $schema->string()->description('Feedback ULID')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'category' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'tool_name' => $schema->string()->description('MCP tool the feedback is about, if any'),
            'summary' => $schema->string()->required(),
            'body' => $schema->string()->description('The full feedback text')->required(),
            'project_id' => $schema->string()->description('Project the feedback was filed against, if any'),
            'agent_id' => $schema->string()->description('Agent that filed the feedback, if any'),
            'user_id' => $schema->string()->description('User that filed the feedback, if any'),
            'created_at' => $schema->string()->required(),
            'updated_at' => $schema->string()->required(),
            'comments' => $schema->array()->description('The comment thread, oldest first')->required(),
        ];
    }
}
