<?php

namespace App\Growth\Alignment;

class AlignmentText
{
    /**
     * Remove legacy labels from messages emitted by older
     * internal services before exposing them through the Growth MCP layer.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    public static function sanitizeArray(array $value): array
    {
        array_walk_recursive($value, function (&$item): void {
            if (is_string($item)) {
                $item = self::sanitize($item);
            }
        });

        return $value;
    }

    public static function sanitize(string $text): string
    {
        $replacements = [
            'requirement quality' => 'Requirement quality',
            'architecture coverage' => 'Architecture coverage',
            'verification evidence' => 'Verification evidence',
            'review readiness' => 'Review evidence',
            'risk management' => 'Risk management',
            'delivery planning' => 'Delivery planning',
            'SRS' => 'requirement definition',
            'SDD' => 'architecture record',
            'MTP' => 'verification plan',
            'PMP' => 'delivery plan',
            'StRS' => 'stakeholder layer',
            'SyRS' => 'system layer',
        ];

        $text = strtr($text, $replacements);
        $text = preg_replace('/§\d+(?:\.\d+)*/', 'rule', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    public static function layerToDoc(string $layer): string
    {
        return match ($layer) {
            'stakeholder' => 'strs',
            'system' => 'syrs',
            default => 'srs',
        };
    }

    public static function docToLayer(string $doc): string
    {
        return match ($doc) {
            'strs' => 'stakeholder',
            'syrs' => 'system',
            default => 'software',
        };
    }
}
