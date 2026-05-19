<?php

namespace App\Growth\Progress;

/**
 * Default {@see ProgressReporter} that discards every update.
 *
 * Services accept this as their default so callers that do not want progress
 * — the webapp, tests, MCP callers that did not supply a `progressToken` —
 * pay nothing and need no special-casing.
 */
class NullProgressReporter implements ProgressReporter
{
    public function report(int|float $progress, int|float|null $total, string $message): void {}
}
