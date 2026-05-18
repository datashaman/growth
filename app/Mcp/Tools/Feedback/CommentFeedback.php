<?php

namespace App\Mcp\Tools\Feedback;

use App\Models\FeedbackComment;
use App\Models\ToolFeedback;
use App\Models\User;
use App\Notifications\FeedbackCommented;
use App\Notifications\WorkspaceNotifier;
use App\Support\RoleContext;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Post a comment on a feedback entry, continuing its thread — ask the filer a follow-up question, or add detail after the original submission. The filer and everyone who has previously commented are notified; the author is not.')]
class CommentFeedback extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'feedback_id' => 'required|string',
            'body' => 'required|string',
        ]);

        $feedback = ToolFeedback::query()
            ->where('workspace_id', app(WorkspaceContext::class)->requireId())
            ->find($data['feedback_id']);

        if ($feedback === null) {
            return new ResponseFactory(Response::error('No feedback with that id exists in the active workspace.'));
        }

        $author = auth()->user();

        $comment = $feedback->comments()->create([
            'user_id' => $author?->getKey(),
            'acting_role' => app(RoleContext::class)->role()?->value,
            'body' => $data['body'],
        ]);

        $this->notifyParticipants($feedback, $comment, $author);

        return Response::structured([
            'id' => $comment->id,
            'feedback_id' => $feedback->id,
            'created' => true,
        ]);
    }

    /**
     * Notify every thread participant except the comment's own author.
     */
    private function notifyParticipants(ToolFeedback $feedback, FeedbackComment $comment, ?User $author): void
    {
        $notifier = app(WorkspaceNotifier::class);

        $feedback->commentParticipants()
            ->reject(fn (User $user): bool => $author !== null && $user->is($author))
            ->each(fn (User $user) => $notifier->notifyUser($user, new FeedbackCommented($comment)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feedback_id' => $schema->string()->description('Feedback ULID to comment on')->required(),
            'body' => $schema->string()->description('The comment text')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'feedback_id' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
