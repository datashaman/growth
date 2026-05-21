<?php

namespace App\Growth\Transitions;

use App\Models\DecisionRequest;
use App\Models\DecisionRequestOption;
use App\Models\User;
use App\Notifications\DecisionRequestAnswered;
use App\Notifications\WorkspaceNotifier;
use Illuminate\Database\Eloquent\Model;

/**
 * Answer an open decision request: record the chosen option and the
 * answerer's rationale, and notify the requester.
 *
 * The answer payload (option, rationale, answerer) is carried on the
 * transition; the rationale also passes through {@see Transition::apply()}
 * as the audit row's reason.
 */
class AnswerDecisionRequest extends DecisionRequestTransition
{
    public function __construct(
        private readonly DecisionRequestOption $option,
        private readonly string $rationale,
        private readonly ?User $answeredBy,
    ) {}

    public function allowedFrom(): array
    {
        return ['open'];
    }

    public function targetStatus(): string
    {
        return 'answered';
    }

    public function verb(): string
    {
        return 'answer';
    }

    public function requiresReason(): bool
    {
        return true;
    }

    protected function decorateSubject(Model $subject, ?string $reason): void
    {
        $subject->setAttribute('chosen_option_id', $this->option->getKey());
        $subject->setAttribute('answer_rationale', $this->rationale);
        $subject->setAttribute('answered_by_user_id', $this->answeredBy?->getKey());
        $subject->setAttribute('answered_at', now());
    }

    /**
     * Tell the requester their decision request was answered. No-op when the
     * request was raised by an agent (no user to reach) or when the requester
     * answered it themselves.
     */
    protected function dispatchNotification(Model $subject, ?User $actor): void
    {
        /** @var DecisionRequest $subject */
        $requester = $subject->requester;

        if ($requester === null) {
            return;
        }

        if ($actor !== null && $requester->is($actor)) {
            return;
        }

        app(WorkspaceNotifier::class)->notifyUser($requester, new DecisionRequestAnswered($subject));
    }
}
