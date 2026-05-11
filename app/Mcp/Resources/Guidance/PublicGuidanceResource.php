<?php

namespace App\Mcp\Resources\Guidance;

use App\Growth\Guidance\PublicGuidanceCatalog;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Metadata for public NASA/NIST guidance sources used to build internal rule packs.')]
#[MimeType('text/markdown')]
class PublicGuidanceResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://guidance/{id}');
    }

    public function handle(Request $request): Response
    {
        $id = (string) $request->get('id');
        $source = PublicGuidanceCatalog::find($id);

        if (! $source) {
            return Response::error('Guidance source ['.$id.'] not supported. Available: '.implode(', ', array_keys(PublicGuidanceCatalog::SOURCES)).'.');
        }

        $textPath = "growth/public-guidance/{$id}.txt";
        $available = Storage::disk('local')->exists($textPath) ? 'yes' : 'no';
        $opportunities = collect($source['rule_pack_opportunities'])
            ->map(fn (string $item) => "- {$item}")
            ->implode("\n");

        $md = <<<MD
# {$source['title']}

**Publisher:** {$source['publisher']}

**Source:** {$source['source_url']}

**Download:** {$source['download_url']}

**Reuse posture:** {$source['license_status']}

**Extracted text available locally:** {$available}

## Rule-Pack Opportunities

{$opportunities}
MD;

        return Response::text($md);
    }
}
