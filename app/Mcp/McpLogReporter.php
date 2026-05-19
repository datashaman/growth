<?php

namespace App\Mcp;

use App\Growth\Logging\LogLevel;
use App\Growth\Logging\LogReporter;
use Laravel\Mcp\Server\Logging\Logging;

/**
 * {@see LogReporter} that forwards each record to the MCP client as a
 * `notifications/message`.
 *
 * Logging is opt-in and gated downstream: the injected {@see Logging} only
 * sends a record when the server advertised the `logging` capability and the
 * record's level is at or above the client's negotiated threshold
 * (`logging/setLevel`). A tool's body is therefore identical whether or not
 * the caller wanted logs — the same graceful no-op as the null reporter.
 */
class McpLogReporter implements LogReporter
{
    public function __construct(private readonly Logging $logging) {}

    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $data = ['message' => $message];

        if ($context !== []) {
            $data['context'] = $context;
        }

        $this->logging->send($level->value, $data);
    }
}
