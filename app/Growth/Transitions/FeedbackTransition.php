<?php

namespace App\Growth\Transitions;

use App\Models\ToolFeedback;
use App\Models\User;
use App\Notifications\FeedbackStatusChanged;
use App\Notifications\WorkspaceNotifier;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for the feedback status transitions (triage, resolve, reopen).
 *
 * Feedback notifications are personal rather than workspace-wide: a
 * transition tells the person who filed the feedback that their submission
 * moved. It reaches no one when the filer made the change themselves, or
 * when the feedback was filed by an agent that has no user account.
 */
abstract class FeedbackTransition extends Transition
{
    public function subjectLabel(): string
    {
        return 'feedback';
    }

    /**
     * Notify the original filer that their feedback changed status.
     *
     * No-op when the feedback was filed by an agent (no user to reach) or
     * when the filer is the actor performing the transition.
     */
    protected function dispatchNotification(Model $subject, ?User $actor): void
    {
        /** @var ToolFeedback $subject */
        $filer = $subject->user;

        if ($filer === null) {
            return;
        }

        if ($actor !== null && $filer->is($actor)) {
            return;
        }

        app(WorkspaceNotifier::class)->notifyUser($filer, new FeedbackStatusChanged($subject));
    }
}
