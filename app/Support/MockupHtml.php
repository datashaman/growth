<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class MockupHtml
{
    public static function withoutOwnerReference(string $html, ?Model $owner): string
    {
        if (! $owner || ! method_exists($owner, 'reference')) {
            return $html;
        }

        $reference = (string) $owner->reference();

        if ($reference === '' || ! str_contains($html, $reference)) {
            return $html;
        }

        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $html;
        }

        foreach ($parts as $index => $part) {
            if ($part === '' || str_starts_with($part, '<')) {
                continue;
            }

            $parts[$index] = self::removeReferenceFromText($part, $reference);
        }

        return implode('', $parts);
    }

    private static function removeReferenceFromText(string $text, string $reference): string
    {
        $quotedReference = preg_quote($reference, '/');
        $separator = '(?:&middot;|&#183;|·|&bull;|&#8226;|•|-|–|—|:)';

        $text = preg_replace('/\b'.$quotedReference.'\b\s*'.$separator.'\s*/u', '', $text) ?? $text;
        $text = preg_replace('/\s*'.$separator.'\s*\b'.$quotedReference.'\b/u', '', $text) ?? $text;
        $text = preg_replace('/\(?\b'.$quotedReference.'\b\)?/u', '', $text) ?? $text;

        return preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;
    }
}
