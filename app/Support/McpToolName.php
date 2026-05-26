<?php

namespace App\Support;

use Illuminate\Support\Str;

class McpToolName
{
    public static function normalize(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = Str::of($name)
            ->trim()
            ->replace('_', '-')
            ->replaceMatches('/-+/', '-')
            ->lower()
            ->trim('-')
            ->toString();

        return $normalized === '' ? null : $normalized;
    }
}
