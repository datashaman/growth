<?php

namespace App\Support;

final class DesignTokenResolver
{
    /** @var list<string> */
    public const TOKENS = ['surface', 'elevation', 'radius', 'spacing_inner'];

    /**
     * Precedence applied low → high; later layers overwrite earlier ones.
     *
     * @var list<string>
     */
    private const PRECEDENCE = ['base', 'mode', 'density', 'component', 'surface', 'state'];

    /**
     * Semantic rule map: [layer][context_value][token] => semantic value.
     * Values are design-system concepts (e.g. 'muted-dark', 'tight', '0'),
     * not raw CSS — the mapping to CSS variables belongs in theme css_tokens.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const RULES = [
        'base' => [
            'default' => [
                'surface' => 'default',
                'elevation' => '1',
                'radius' => 'default',
                'spacing_inner' => 'default',
            ],
        ],
        'mode' => [
            'dark' => [
                'surface' => 'muted-dark',
            ],
        ],
        'density' => [
            'compact' => [
                'spacing_inner' => 'tight',
                'radius' => 'tight',
            ],
            'comfortable' => [
                'spacing_inner' => 'loose',
            ],
        ],
        'component' => [
            'card' => ['elevation' => '1'],
            'button' => ['radius' => 'full', 'elevation' => '0'],
            'table' => ['radius' => 'none', 'elevation' => '0'],
        ],
        'surface' => [
            'form' => ['elevation' => '0', 'surface' => 'muted'],
            'panel' => ['elevation' => '1'],
            'dialog' => ['elevation' => '3', 'surface' => 'overlay'],
        ],
        'state' => [
            'disabled' => ['surface' => 'muted', 'elevation' => '0'],
            'hover' => ['elevation' => '2'],
        ],
    ];

    /**
     * @param  array{mode?:string,density?:string,surface?:string,component?:string,state?:string}  $context
     * @return array<string, array{value:string,reason:string,source:string}>
     */
    public function resolve(array $context): array
    {
        $resolved = [];
        $trace = [];

        foreach (self::PRECEDENCE as $layer) {
            $contextValue = $layer === 'base' ? 'default' : ($context[$layer] ?? null);

            if ($contextValue === null) {
                continue;
            }

            foreach (self::RULES[$layer][$contextValue] ?? [] as $token => $value) {
                $resolved[$token] = $value;
                $trace[$token] = [$layer, $contextValue];
            }
        }

        foreach (self::TOKENS as $token) {
            if (! isset($resolved[$token])) {
                $resolved[$token] = self::RULES['base']['default'][$token];
                $trace[$token] = ['base', 'default'];
            }
        }

        return $this->annotate($resolved, $trace);
    }

    /**
     * @param  array<string, string>  $resolved
     * @param  array<string, array{0:string,1:string}>  $trace
     * @return array<string, array{value:string,reason:string,source:string}>
     */
    private function annotate(array $resolved, array $trace): array
    {
        $out = [];

        foreach ($resolved as $token => $value) {
            [$layer, $ctx] = $trace[$token];
            $out[$token] = [
                'value' => $value,
                'reason' => $layer === 'base'
                    ? 'No context matched; base default applied.'
                    : "Overridden by {$layer} rule for \"{$ctx}\".",
                'source' => "{$layer}:{$ctx}",
            ];
        }

        return $out;
    }
}
