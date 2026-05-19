<?php

namespace App\Mcp\Tools\Feedback;

use App\Growth\Sampling\SamplingGateway;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\TriageFeedback as TriageFeedbackTransition;
use App\Mcp\McpSamplingGateway;
use App\Models\ToolFeedback;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Sampling\Sampling;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Triage submitted feedback: move it from new to triaged. Rejects feedback that is not new with a clear message. Records a status transition with the acting user and timestamp. When no reason is supplied and the client supports MCP sampling, drafts a triage rationale from the feedback content.')]
class TriageFeedback extends Tool
{
    public function handle(Request $request, Sampling $sampling): ResponseFactory
    {
        $data = $request->validate([
            'feedback_id' => 'required|string',
            'reason' => 'nullable|string|max:1000',
        ]);

        $feedback = ToolFeedback::query()
            ->where('workspace_id', app(WorkspaceContext::class)->requireId())
            ->find($data['feedback_id']);

        if ($feedback === null) {
            return new ResponseFactory(Response::error('No feedback with that id exists in the active workspace.'));
        }

        $reason = $data['reason'] ?? null;

        if ($reason === null || trim($reason) === '') {
            $reason = $this->draftReason($feedback, new McpSamplingGateway($sampling));
        }

        try {
            $transition = (new TriageFeedbackTransition)->apply($feedback, auth()->user(), $reason);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'feedback_id' => $feedback->id,
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'transition_id' => $transition->id,
            'transitioned_at' => $transition->transitioned_at->toIso8601String(),
        ]);
    }

    /**
     * Ask the MCP client's model to draft a triage rationale from the feedback
     * content. Returns `null` when the client cannot sample, so the transition
     * is still recorded — just without a note.
     */
    private function draftReason(ToolFeedback $feedback, SamplingGateway $sampling): ?string
    {
        $prompt = implode("\n", array_filter([
            $feedback->category !== null ? "Category: {$feedback->category}" : null,
            $feedback->tool_name !== null ? "Tool: {$feedback->tool_name}" : null,
            $feedback->summary !== null ? "Summary: {$feedback->summary}" : null,
            $feedback->body !== null ? "Details: {$feedback->body}" : null,
        ]));

        $text = $sampling->requestText(
            $prompt,
            200,
            'You are triaging user feedback for Growth, a project-governance tool. In one or two sentences, state a triage rationale: what the feedback concerns and why it is being moved to triaged. Reply with only the rationale.',
        );

        if ($text === null) {
            return null;
        }

        return mb_substr(trim($text), 0, 900)."\n\n— drafted via MCP sampling";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feedback_id' => $schema->string()->description('Feedback ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the transition. When omitted, a rationale is drafted via MCP sampling if the client supports it.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'feedback_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'transition_id' => $schema->string()->required(),
            'transitioned_at' => $schema->string()->required(),
        ];
    }
}
