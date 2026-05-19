<?php

namespace App\Growth\Logging;

/**
 * Default {@see LogReporter} that discards every record.
 *
 * Services accept this as their default so callers that do not want logging
 * — the webapp, tests, MCP callers whose client never enabled logging — pay
 * nothing and need no special-casing.
 */
class NullLogReporter implements LogReporter
{
    public function log(LogLevel $level, string $message, array $context = []): void {}
}
