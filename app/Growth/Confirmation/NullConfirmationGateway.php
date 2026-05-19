<?php

namespace App\Growth\Confirmation;

/**
 * Default {@see ConfirmationGateway} that never collects a confirmation.
 *
 * Services accept this as their default so callers that cannot elicit — the
 * webapp, tests, MCP callers whose client did not declare the `elicitation`
 * capability — need no special-casing and simply fall back to another guard.
 */
class NullConfirmationGateway implements ConfirmationGateway
{
    public function confirm(string $message): ?bool
    {
        return null;
    }
}
