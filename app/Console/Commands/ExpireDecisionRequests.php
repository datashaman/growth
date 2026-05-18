<?php

namespace App\Console\Commands;

use App\Growth\Transitions\ExpireDecisionRequest;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\DecisionRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('decision-requests:expire')]
#[Description('Expire open decision requests whose deadline has passed, recording an auditable status transition for each.')]
class ExpireDecisionRequests extends Command
{
    public function handle(): int
    {
        // Runs unauthenticated, so the ScopedByOwner global scope is a no-op
        // and the scan covers overdue requests across every workspace.
        $overdue = DecisionRequest::query()
            ->where('status', 'open')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->get();

        $expired = 0;

        foreach ($overdue as $decisionRequest) {
            try {
                (new ExpireDecisionRequest)->apply($decisionRequest);
                $expired++;
            } catch (IllegalTransitionException) {
                // A concurrent answer or cancellation already moved it on.
            }
        }

        $this->info("Expired {$expired} decision request(s).");

        return self::SUCCESS;
    }
}
