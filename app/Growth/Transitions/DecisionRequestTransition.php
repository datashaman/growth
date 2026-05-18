<?php

namespace App\Growth\Transitions;

/**
 * Base for the decision-request status transitions (answer, cancel, expire).
 *
 * Decision requests have a simple lifecycle, so they ride the generic
 * verb-named {@see Transition} machinery and the polymorphic
 * `status_transitions` audit table — no bespoke event log.
 */
abstract class DecisionRequestTransition extends Transition
{
    public function subjectLabel(): string
    {
        return 'decision request';
    }
}
