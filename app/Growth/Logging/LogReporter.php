<?php

namespace App\Growth\Logging;

/**
 * Receives structured log records from a long-running service.
 *
 * Implementations are MCP-agnostic by design: a service depends only on this
 * contract, never on the transport that ultimately delivers the record. The
 * MCP layer supplies an implementation that forwards each record to the client
 * as a `notifications/message`; everywhere else {@see NullLogReporter} makes
 * logging a no-op.
 */
interface LogReporter
{
    /**
     * Emit a structured log record.
     *
     * @param  LogLevel  $level  Severity of the record.
     * @param  string  $message  Human-readable description of what happened.
     * @param  array<string,mixed>  $context  Optional structured detail accompanying the message.
     */
    public function log(LogLevel $level, string $message, array $context = []): void;
}
