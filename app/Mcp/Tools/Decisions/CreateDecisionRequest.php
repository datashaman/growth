<?php

namespace App\Mcp\Tools\Decisions;

use App\Models\DecisionRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DecisionRequestRaised;
use App\Notifications\WorkspaceNotifier;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Raise a decision request: ask a named role to choose between options. Routes the question to the role\'s queue and notifies everyone assigned to it. Optionally links the request to an artifact and sets a deadline. Answer it later with answer-decision-request.')]
class CreateDecisionRequest extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'target_role_id' => 'required|string|owned_role',
            'question' => 'required|string|max:2000',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'required|string|max:255',
            'deadline' => 'nullable|date',
            'subject_type' => 'nullable|string|in:'.implode(',', array_keys(AppServiceProvider::MORPH_MAP)),
            'subject_id' => 'nullable|string|required_with:subject_type',
        ]);

        $role = Role::findOrFail($data['target_role_id']);

        $decisionRequest = DB::transaction(function () use ($data, $role): DecisionRequest {
            $decisionRequest = DecisionRequest::create([
                'project_id' => $role->project_id,
                'requester_user_id' => auth()->id(),
                'target_role_id' => $role->id,
                'question' => $data['question'],
                'status' => 'open',
                'deadline' => $data['deadline'] ?? null,
                'subjectable_type' => $data['subject_type'] ?? null,
                'subjectable_id' => $data['subject_id'] ?? null,
            ]);

            foreach (array_values($data['options']) as $position => $label) {
                $decisionRequest->options()->create([
                    'label' => $label,
                    'position' => $position,
                ]);
            }

            return $decisionRequest;
        });

        $this->notifyAssignees($role, $decisionRequest);

        return Response::structured([
            'id' => $decisionRequest->id,
            'status' => $decisionRequest->status,
            'target_role_id' => $role->id,
            'options' => $decisionRequest->options->map(fn ($option): array => [
                'id' => $option->id,
                'label' => $option->label,
            ])->all(),
            'created' => true,
        ]);
    }

    /**
     * Notify everyone assigned to the target role, except the requester.
     */
    private function notifyAssignees(Role $role, DecisionRequest $decisionRequest): void
    {
        $requesterId = auth()->id();

        $notifier = app(WorkspaceNotifier::class);

        $role->users()
            ->get()
            ->reject(fn (User $user): bool => $requesterId !== null && (string) $user->getKey() === (string) $requesterId)
            ->each(fn (User $user) => $notifier->notifyUser($user, new DecisionRequestRaised($decisionRequest)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'target_role_id' => $schema->string()->description('Role ULID to route the decision to')->required(),
            'question' => $schema->string()->description('The decision to be made')->required(),
            'options' => $schema->array()->description('The choices to decide between (2-10)')->required(),
            'deadline' => $schema->string()->description('Optional ISO-8601 deadline; an open request past it is expired'),
            'subject_type' => $schema->string()->description('Optional artifact type the decision is about, e.g. change_request'),
            'subject_id' => $schema->string()->description('Optional artifact id, required when subject_type is given'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'target_role_id' => $schema->string()->required(),
            'options' => $schema->array()->description('The created options, each with id and label')->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
