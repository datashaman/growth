<?php

namespace App\Console\Commands;

use App\Models\ToolInvocation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('tool-invocations:prune {--days=90}')]
#[Description('Delete tool_invocations rows older than the cutoff (default 90 days).')]
class PruneToolInvocations extends Command
{
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $deleted = ToolInvocation::where('started_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} tool invocation rows older than {$days} days.");

        return self::SUCCESS;
    }
}
