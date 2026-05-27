<?php

namespace App\Mcp\Tools\Feedback;

use App\Models\ToolFeedback;
use App\Support\RoleContext;
use App\Support\SurfaceContext;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Update safe editable fields on an existing feedback entry without creating a duplicate. Preserves the feedback id, comments, status transitions, and thread history; records an attributed audit comment when fields change.')]
class UpdateFeedback extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'feedback_id' => 'required|string',
            'category' => 'sometimes|required|string|in:'.implode(',', ToolFeedback::CATEGORIES),
            'summary' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'tool_name' => 'sometimes|nullable|string|max:120',
            'status' => 'prohibited',
        ], [
            'status.prohibited' => 'Feedback status is not updated here. Use the feedback transition tools (triage-feedback, resolve-feedback, reopen-feedback) to move status.',
        ]);

        $fields = array_intersect_key($data, array_flip(['category', 'summary', 'body', 'tool_name']));
        if ($fields === []) {
            throw ValidationException::withMessages([
                'feedback_id' => 'Provide at least one editable field: category, summary, body, or tool_name.',
            ]);
        }

        $feedback = ToolFeedback::query()
            ->where('workspace_id', app(WorkspaceContext::class)->requireId())
            ->find($data['feedback_id']);

        if ($feedback === null) {
            return new ResponseFactory(Response::error('No feedback with that id exists in the active workspace.'));
        }

        $changed = DB::transaction(function () use ($feedback, $fields): array {
            $feedback->fill($fields);
            $changed = array_keys($feedback->getDirty());

            if ($changed === []) {
                return [];
            }

            $feedback->save();
            $this->recordAuditComment($feedback, $changed);

            return $changed;
        });

        $feedback->refresh();

        return Response::structured([
            'id' => $feedback->id,
            'updated' => $changed !== [],
            'changed_fields' => $changed,
            'category' => $feedback->category,
            'status' => $feedback->status,
            'tool_name' => $feedback->tool_name,
            'summary' => $feedback->summary,
            'body' => $feedback->body,
            'updated_at' => $feedback->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  list<string>  $changed
     */
    private function recordAuditComment(ToolFeedback $feedback, array $changed): void
    {
        $actingRole = app(RoleContext::class)->role();
        $fields = implode(', ', $changed);

        $feedback->comments()->create([
            'user_id' => auth()->user()?->getKey(),
            'acting_surface' => app(SurfaceContext::class)->surface()?->value,
            'acting_role_id' => $actingRole?->getKey(),
            'acting_role_name' => $actingRole?->name,
            'body' => 'Updated feedback fields: '.$fields.'.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feedback_id' => $schema->string()->description('Feedback ULID to update')->required(),
            'category' => $schema->string()->description('Corrected feedback category')->enum(ToolFeedback::CATEGORIES),
            'summary' => $schema->string()->description('Corrected one-line summary, max 255 chars'),
            'body' => $schema->string()->description('Corrected full feedback body'),
            'tool_name' => $schema->string()->description('Corrected MCP tool name, or null to clear it'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'updated' => $schema->boolean()->required(),
            'changed_fields' => $schema->array()->required(),
            'category' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'tool_name' => $schema->string(),
            'summary' => $schema->string()->required(),
            'body' => $schema->string()->required(),
            'updated_at' => $schema->string()->required(),
        ];
    }
}
