<?php

namespace App\Growth\Progress;

/**
 * Receives per-phase progress updates from a long-running service.
 *
 * Implementations are MCP-agnostic by design: a service depends only on this
 * contract, never on the transport that ultimately delivers the update. The
 * MCP layer supplies an implementation that forwards to a client; everywhere
 * else {@see NullProgressReporter} makes progress reporting a no-op.
 */
interface ProgressReporter
{
    /**
     * Report that a phase of work has completed.
     *
     * @param  int|float  $progress  Work completed so far; must increase with each call.
     * @param  int|float|null  $total  Total work expected, when known.
     * @param  string  $message  Human-readable description of the phase just finished.
     */
    public function report(int|float $progress, int|float|null $total, string $message): void;
}
