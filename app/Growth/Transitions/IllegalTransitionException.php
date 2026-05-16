<?php

namespace App\Growth\Transitions;

use RuntimeException;

/**
 * Raised when a transition action is applied to a subject whose current
 * status is not an accepted source state for that transition.
 */
class IllegalTransitionException extends RuntimeException {}
