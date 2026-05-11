<?php

namespace App\Mcp\Resources\Support;

use Illuminate\Support\Collection;

class CitationRenderer
{
    /**
     * Render a Markdown sub-bullet block for an artifact's citations.
     * Returns an empty string when the artifact has no citations.
     *
     * The artifact's citations relation must already be eager-loaded with
     * source — calling this lazily would N+1.
     */
    public static function render(Collection $citations, string $indent = '  '): string
    {
        if ($citations->isEmpty()) {
            return '';
        }

        $out = '';
        foreach ($citations as $c) {
            $source = $c->source;
            if (! $source) {
                continue;
            }

            $title = $source->uri
                ? "[{$source->title}]({$source->uri})"
                : $source->title;

            $line = "{$indent}- _Cites:_ {$title}";
            if ($c->locator) {
                $line .= " — {$c->locator}";
            }
            if ($c->quote) {
                $line .= ' — "'.$c->quote.'"';
            }
            $out .= $line."\n";
        }

        return $out;
    }
}
