<?php

namespace App\Console\Commands;

use App\Growth\Guidance\PublicGuidanceCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

#[Signature('growth:ingest-public-guidance {id? : Guidance id to ingest, or omit for all} {--source= : Directory containing already-downloaded PDFs} {--download : Download missing PDFs from official public URLs}')]
#[Description('Extract approved NASA/NIST public guidance PDFs into storage/app/growth/public-guidance for bounded MCP search.')]
class IngestPublicGuidanceCommand extends Command
{
    public function handle(): int
    {
        $ids = $this->argument('id')
            ? [$this->argument('id')]
            : array_keys(PublicGuidanceCatalog::SOURCES);

        foreach ($ids as $id) {
            $source = PublicGuidanceCatalog::find($id);
            if (! $source) {
                $this->error("Guidance source [{$id}] not supported.");

                return self::FAILURE;
            }

            $pdf = $this->resolvePdf($id, $source);
            if (! $pdf) {
                $this->warn("Skipping {$id}: no PDF found. Provide --source or pass --download.");

                continue;
            }

            $process = new Process(['pdftotext', '-layout', $pdf, '-']);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error("pdftotext failed for {$id}: ".$process->getErrorOutput());

                return self::FAILURE;
            }

            Storage::disk('local')->put("growth/public-guidance/{$id}.txt", $process->getOutput());
            Storage::disk('local')->put("growth/public-guidance/{$id}.json", json_encode([
                'id' => $id,
                'title' => $source['title'],
                'source_url' => $source['source_url'],
                'download_url' => $source['download_url'],
                'license_status' => $source['license_status'],
                'ingested_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->info("Ingested {$id}: {$source['title']}");
        }

        return self::SUCCESS;
    }

    private function resolvePdf(string $id, array $source): ?string
    {
        $sourceDir = $this->option('source');
        if ($sourceDir) {
            $candidate = rtrim($sourceDir, '/')."/{$id}.pdf";
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $cachePath = Storage::disk('local')->path("growth/public-guidance/{$id}.pdf");
        if (is_file($cachePath)) {
            return $cachePath;
        }

        if (! $this->option('download')) {
            return null;
        }

        $response = Http::timeout(60)->get($source['download_url']);
        if (! $response->successful()) {
            $this->warn("Download failed for {$id}: HTTP {$response->status()}");

            return null;
        }

        if (! is_dir(dirname($cachePath))) {
            mkdir(dirname($cachePath), 0777, true);
        }
        file_put_contents($cachePath, $response->body());

        return $cachePath;
    }
}
