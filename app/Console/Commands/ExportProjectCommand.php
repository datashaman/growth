<?php

namespace App\Console\Commands;

use App\Growth\Export\ProjectExporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('growth:export-project {project : Project id or exact project name} {path : Directory to write export files into}')]
#[Description('Export a Growth project snapshot as Markdown and JSON artifacts.')]
class ExportProjectCommand extends Command
{
    public function handle(ProjectExporter $exporter): int
    {
        try {
            $summary = $exporter->export(
                $this->argument('project'),
                $this->argument('path'),
            );
        } catch (Throwable $exception) {
            if (app()->environment('testing')) {
                throw $exception;
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Exported {$summary['project_name']} ({$summary['project_id']})");
        $this->line("Path: {$summary['path']}");
        $this->line("Requirements: {$summary['requirements']}");
        $this->line("Sources: {$summary['sources']}");
        $this->line("Work items: {$summary['work_items']}");
        $this->line('Files: '.implode(', ', $summary['files']));

        return self::SUCCESS;
    }
}
