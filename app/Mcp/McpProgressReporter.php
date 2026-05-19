<?php

namespace App\Mcp;

use App\Growth\Progress\NullProgressReporter;
use App\Growth\Progress\ProgressReporter;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Notifications\ProgressNotification;

/**
 * {@see ProgressReporter} that forwards each phase update to the MCP client as
 * a `notifications/progress` message, tagged with the originating request's
 * `progressToken`.
 *
 * Progress is opt-in: a client signals interest by including
 * `params._meta.progressToken` on the `tools/call` request. When no token is
 * present {@see forRequest()} returns a {@see NullProgressReporter} instead, so
 * a tool's body is identical whether or not the caller wanted progress.
 */
class McpProgressReporter implements ProgressReporter
{
    public function __construct(
        private readonly ProgressNotification $notification,
        private readonly string|int $progressToken,
    ) {}

    /**
     * Build the reporter appropriate to a tool request: a real reporter when
     * the caller supplied a `progressToken`, otherwise a no-op.
     */
    public static function forRequest(Request $request, ProgressNotification $notification): ProgressReporter
    {
        $token = $request->meta()['progressToken'] ?? null;

        return is_string($token) || is_int($token)
            ? new self($notification, $token)
            : new NullProgressReporter;
    }

    public function report(int|float $progress, int|float|null $total, string $message): void
    {
        $this->notification->send($this->progressToken, $progress, $total, $message);
    }
}
