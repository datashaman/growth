<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('rejects unsupported public guidance ids', function () {
    $this->artisan('growth:ingest-public-guidance', ['id' => 'unknown'])
        ->expectsOutput('Guidance source [unknown] not supported.')
        ->assertExitCode(1);
});

it('skips sources when no local pdf or download option is provided', function () {
    $source = storage_path('framework/testing/public-guidance-source');
    if (! is_dir($source)) {
        mkdir($source, 0777, true);
    }

    $this->artisan('growth:ingest-public-guidance', [
        'id' => 'nasa-risk',
        '--source' => $source,
    ])
        ->expectsOutput('Skipping nasa-risk: no PDF found. Provide --source or pass --download.')
        ->assertExitCode(0);

    Storage::disk('local')->assertMissing('growth/public-guidance/nasa-risk.txt');
    Storage::disk('local')->assertMissing('growth/public-guidance/nasa-risk.json');
});

it('recognizes the NIST secure development guidance id', function () {
    $this->artisan('growth:ingest-public-guidance', ['id' => 'nist-ssdf'])
        ->expectsOutput('Skipping nist-ssdf: no PDF found. Provide --source or pass --download.')
        ->assertExitCode(0);
});
