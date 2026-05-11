<?php

namespace App\Console\Commands;

use App\Growth\Ingest\LocalProjectIngestor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('growth:ingest-local-project {path : Local repository or project directory} {--name= : Project name to create/update} {--integrity=2 : Project integrity level} {--limit-issues=50 : Number of GitHub issues to import as ticket sources and work items} {--limit-prs=50 : Number of merged GitHub pull requests to import as delivery evidence} {--limit-commits=20 : Number of recent commits to import as completed work items}')]
#[Description('Import local project documentation and recent git history into Growth.')]
class IngestLocalProjectCommand extends Command
{
    public function handle(LocalProjectIngestor $ingestor): int
    {
        try {
            $summary = $ingestor->ingest($this->argument('path'), [
                'name' => $this->option('name'),
                'integrity_level' => $this->option('integrity'),
                'issue_limit' => $this->option('limit-issues'),
                'pr_limit' => $this->option('limit-prs'),
                'commit_limit' => $this->option('limit-commits'),
            ]);
        } catch (Throwable $exception) {
            if (app()->environment('testing')) {
                throw $exception;
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$summary['project_name']} ({$summary['project_id']})");
        $this->line("Path: {$summary['path']}");
        $this->line("Sources: {$summary['sources']}");
        $this->line("Requirements: {$summary['requirements']}");
        $this->line("Issues: {$summary['issues']}");
        $this->line("Pull requests: {$summary['pull_requests']}");
        $this->line("Work items: {$summary['work_items']}");

        return self::SUCCESS;
    }
}
