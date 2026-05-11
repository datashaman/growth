<?php

namespace App\Growth\Glossary;

class GlossaryParser
{
    /**
     * Parse glossary-formatted vocabulary text into term entries.
     *
     * Entries look like:
     *   <section>           e.g. "3.14"
     *   <term>              e.g. "absolute address"
     *   1. <definition>
     *   2. <definition>
     *   cf. ...
     *
     * @return list<array{section:string,term:string,body:string}>
     */
    public function parse(string $text): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $entries = [];
        $sectionRe = '/^\d+\.\d+(?:\.\d+)?$/';

        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            $line = trim($lines[$i]);
            if ($line === '' || ! preg_match($sectionRe, $line)) {
                $i++;

                continue;
            }

            $section = $line;
            $j = $i + 1;
            while ($j < $n && trim($lines[$j]) === '') {
                $j++;
            }
            if ($j >= $n) {
                break;
            }
            $term = trim($lines[$j]);
            if ($term === '' || preg_match($sectionRe, $term)) {
                $i = $j;

                continue;
            }

            $bodyLines = [];
            $k = $j + 1;
            while ($k < $n) {
                $candidate = trim($lines[$k]);
                if (preg_match($sectionRe, $candidate)) {
                    break;
                }
                $bodyLines[] = $lines[$k];
                $k++;
            }

            $entries[] = [
                'section' => $section,
                'term' => $term,
                'body' => trim(implode("\n", $bodyLines)),
            ];

            $i = $k;
        }

        return $entries;
    }
}
