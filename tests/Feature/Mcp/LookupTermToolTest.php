<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Glossary\LookupTerm;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;

beforeEach(function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
});

it('finds a term with the default contains match', function () {
    ReadonlyServer::tool(LookupTerm::class, ['query' => 'milestone'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('count', 1)
                ->where('matches.0.term', 'milestone')
                ->etc();
        });
});

it('matches exactly in exact mode', function () {
    ReadonlyServer::tool(LookupTerm::class, ['query' => 'baseline', 'mode' => 'exact'])
        ->assertStructuredContent(fn ($json) => $json->where('count', 1)->etc());

    ReadonlyServer::tool(LookupTerm::class, ['query' => 'baselin', 'mode' => 'exact'])
        ->assertStructuredContent(fn ($json) => $json->where('count', 0)->etc());
});

it('matches on a leading fragment in prefix mode', function () {
    ReadonlyServer::tool(LookupTerm::class, ['query' => 'verification', 'mode' => 'prefix'])
        ->assertStructuredContent(fn ($json) => $json->where('count', 3)->etc());
});

it('caps the result set at the requested limit', function () {
    ReadonlyServer::tool(LookupTerm::class, ['query' => 'e', 'limit' => 5])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('count', 5)->has('matches', 5)->etc();
        });
});

it('returns an empty result, not an error, for an unknown term', function () {
    ReadonlyServer::tool(LookupTerm::class, ['query' => 'zzznotarealterm'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('count', 0)->where('matches', [])->etc();
        });
});

it('returns an empty result, not an error, when the glossary file is absent', function () {
    $path = resource_path('glossary/glossary-extract.txt');
    $backup = $path.'.testbak';
    File::move($path, $backup);

    try {
        ReadonlyServer::tool(LookupTerm::class, ['query' => 'workspace'])
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->where('count', 0)->where('matches', [])->etc();
            });
    } finally {
        File::move($backup, $path);
    }
});
