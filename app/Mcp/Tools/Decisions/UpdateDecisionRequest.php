<?php

namespace App\Mcp\Tools\Decisions;

use App\Models\DecisionRequest;
use App\Support\RoleContext;
use App\Support\SurfaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Update safe editable fields on an open decision request without cancelling and recreating it. Preserves the request id, target role queue placement, and status history while recording an attributed audit row.')]
class UpdateDecisionRequest extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'decision_request_id' => 'required|string|owned_decision_request',
            'question' => 'sometimes|required|string|max:2000',
            'deadline' => 'sometimes|nullable|date',
            'options' => 'sometimes|required|array|min:2|max:10',
            'options.*' => 'required|string|max:255',
            'status' => 'prohibited',
            'target_role_id' => 'prohibited',
        ], [
            'status.prohibited' => 'Decision request status is not updated here. Use answer-decision-request, cancel-decision-request, or expiry processing to move status.',
            'target_role_id.prohibited' => 'Decision request queue placement is preserved by this update path.',
        ]);

        $editableFields = array_intersect_key($data, array_flip(['question', 'deadline', 'options']));
        if ($editableFields === []) {
            throw ValidationException::withMessages([
                'decision_request_id' => 'Provide at least one editable field: question, deadline, or options.',
            ]);
        }

        $decisionRequest = DecisionRequest::with(['options', 'targetRole'])->findOrFail($data['decision_request_id']);

        if ((string) $decisionRequest->requester_user_id !== (string) auth()->id()) {
            return new ResponseFactory(Response::error('Only the user who raised this decision request may update it.'));
        }

        if ($decisionRequest->status !== 'open') {
            return new ResponseFactory(Response::error('Only open decision requests can be updated.'));
        }

        $changed = DB::transaction(function () use ($decisionRequest, $data): array {
            $locked = DecisionRequest::query()
                ->with('options')
                ->lockForUpdate()
                ->findOrFail($decisionRequest->id);

            if ($locked->status !== 'open') {
                return [];
            }

            $changed = [];

            if (array_key_exists('question', $data) && $locked->question !== $data['question']) {
                $locked->question = $data['question'];
                $changed[] = 'question';
            }

            if (array_key_exists('deadline', $data) && $this->deadlineChanged($locked, $data['deadline'])) {
                $locked->deadline = $data['deadline'];
                $changed[] = 'deadline';
            }

            if (array_key_exists('options', $data) && $this->optionsChanged($locked, $data['options'])) {
                $locked->options()->delete();
                foreach (array_values($data['options']) as $position => $label) {
                    $locked->options()->create([
                        'label' => $label,
                        'position' => $position,
                    ]);
                }
                $changed[] = 'options';
            }

            if ($changed === []) {
                return [];
            }

            $locked->save();
            $this->recordAudit($locked, $changed);

            return $changed;
        });

        $decisionRequest->refresh()->load(['options', 'targetRole']);

        return Response::structured([
            'id' => $decisionRequest->id,
            'updated' => $changed !== [],
            'changed_fields' => $changed,
            'status' => $decisionRequest->status,
            'target_role_id' => $decisionRequest->target_role_id,
            'target_role' => $decisionRequest->targetRole?->name,
            'question' => $decisionRequest->question,
            'deadline' => $decisionRequest->deadline?->toIso8601String(),
            'options' => $decisionRequest->options->map(fn ($option): array => [
                'id' => $option->id,
                'label' => $option->label,
            ])->all(),
            'updated_at' => $decisionRequest->updated_at?->toIso8601String(),
        ]);
    }

    private function deadlineChanged(DecisionRequest $decisionRequest, mixed $deadline): bool
    {
        $current = $decisionRequest->deadline?->toIso8601String();
        $next = $deadline === null ? null : Carbon::parse($deadline)->toIso8601String();

        return $current !== $next;
    }

    /**
     * @param  list<string>  $options
     */
    private function optionsChanged(DecisionRequest $decisionRequest, array $options): bool
    {
        return $decisionRequest->options
            ->pluck('label')
            ->values()
            ->all() !== array_values($options);
    }

    /**
     * @param  list<string>  $changed
     */
    private function recordAudit(DecisionRequest $decisionRequest, array $changed): void
    {
        $actingRole = app(RoleContext::class)->role();

        $decisionRequest->statusTransitions()->create([
            'from_status' => 'open',
            'to_status' => 'open',
            'reason' => 'Updated decision request fields: '.implode(', ', $changed).'.',
            'transitioned_by_user_id' => auth()->id(),
            'acting_surface' => app(SurfaceContext::class)->surface()?->value,
            'acting_role_id' => $actingRole?->getKey(),
            'acting_role_name' => $actingRole?->name,
            'transitioned_at' => now(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'decision_request_id' => $schema->string()->description('DecisionRequest ULID to update')->required(),
            'question' => $schema->string()->description('Corrected decision question'),
            'deadline' => $schema->string()->description('Corrected ISO-8601 deadline, or null to clear it'),
            'options' => $schema->array()->description('Corrected choices to decide between (2-10)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'updated' => $schema->boolean()->required(),
            'changed_fields' => $schema->array()->required(),
            'status' => $schema->string()->required(),
            'target_role_id' => $schema->string()->required(),
            'target_role' => $schema->string(),
            'question' => $schema->string()->required(),
            'deadline' => $schema->string(),
            'options' => $schema->array()->required(),
            'updated_at' => $schema->string()->required(),
        ];
    }
}
