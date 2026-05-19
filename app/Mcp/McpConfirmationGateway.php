<?php

namespace App\Mcp;

use App\Growth\Confirmation\ConfirmationGateway;
use App\Growth\Confirmation\NullConfirmationGateway;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Elicitation\Elicitation;
use Laravel\Mcp\Server\Elicitation\ElicitSchema;

/**
 * {@see ConfirmationGateway} that collects the confirmation from the connected
 * MCP client via a form-mode `elicitation/create` request.
 *
 * The round-trip is gated by the client: {@see Elicitation::form()} throws when
 * the client never declared the `elicitation` capability, and the client may
 * itself decline or cancel the form. A thrown request becomes `null` — the
 * same "no confirmation available" contract as {@see NullConfirmationGateway} —
 * while an explicit decline or cancel becomes `false`.
 */
class McpConfirmationGateway implements ConfirmationGateway
{
    public function __construct(private readonly Elicitation $elicitation) {}

    public function confirm(string $message): ?bool
    {
        try {
            $result = $this->elicitation->form(
                $message,
                fn (ElicitSchema $schema): array => [
                    'confirm' => $schema->boolean('Confirm')
                        ->description('Check to proceed; leave unchecked to abort.')
                        ->required(),
                ],
            );
        } catch (JsonRpcException) {
            return null;
        }

        if (! $result->accepted()) {
            return false;
        }

        return $result->get('confirm') === true;
    }
}
