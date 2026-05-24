<?php

namespace App\Support;

use App\Models\Theme;

class ThemePreviewSpecimen
{
    /**
     * @return array{description:string,guidance:list<string>,selectors:list<array{role:string,selector:string,purpose:string}>,sample_html:string,resolution_example:array{context:array<string,string>,tokens:array<string,array{value:string,reason:string,source:string}>}}
     */
    public static function contract(): array
    {
        $exampleContext = ['density' => 'compact', 'surface' => 'form', 'state' => 'disabled'];

        return [
            'description' => 'Stable selector contract for the theme preview specimen used by the Themes page and MCP theme debugging.',
            'guidance' => [
                'Theme CSS should style these semantic selectors rather than guessing private page markup.',
                'Use body/main for preview chrome defaults, then reset panel/table content when the panel surface is light.',
                'Reusable visual language belongs in theme raw_css, tokens, and design notes; mockup HTML should stay mostly semantic.',
            ],
            'selectors' => [
                ['role' => 'preview_chrome', 'selector' => 'body, main, [data-preview-role="chrome"]', 'purpose' => 'Overall preview canvas and top-level text defaults.'],
                ['role' => 'preview_kicker', 'selector' => '.label, [data-preview-role="kicker"]', 'purpose' => 'Small uppercase label above the preview title.'],
                ['role' => 'preview_title', 'selector' => 'h1, [data-preview-role="title"]', 'purpose' => 'Primary preview heading.'],
                ['role' => 'active_status', 'selector' => '.status, button.active, [data-preview-role="active-status"]', 'purpose' => 'Active/live status control.'],
                ['role' => 'primary_action', 'selector' => '.button.primary, button.primary, [data-preview-role="primary-action"]', 'purpose' => 'Primary call-to-action button.'],
                ['role' => 'secondary_action', 'selector' => '.button.secondary, button.secondary, [data-preview-role="secondary-action"]', 'purpose' => 'Secondary action button.'],
                ['role' => 'panel_surface', 'selector' => '.panel, [data-preview-role="panel"]', 'purpose' => 'Main sample content surface.'],
                ['role' => 'accent_bar', 'selector' => '.bar, [data-preview-role="bar"]', 'purpose' => 'Prominent accent bar in the panel.'],
                ['role' => 'metric_blocks', 'selector' => '.metric, [data-preview-role="metric"]', 'purpose' => 'Repeated metric tiles.'],
                ['role' => 'form_controls', 'selector' => 'label, input, select, textarea, [data-preview-role="form"]', 'purpose' => 'Form labels, fields, select controls, and text areas.'],
                ['role' => 'sparkline', 'selector' => '.spark, [data-preview-role="spark"]', 'purpose' => 'Secondary accent strip.'],
                ['role' => 'warning_state', 'selector' => '.warn, [data-preview-role="warning"]', 'purpose' => 'Warning/attention state.'],
                ['role' => 'success_state', 'selector' => '.success, [data-preview-role="success"]', 'purpose' => 'Positive confirmation state.'],
                ['role' => 'badge_state', 'selector' => '.badge, [data-preview-role="badge"]', 'purpose' => 'Compact labels and status badges.'],
                ['role' => 'list_content', 'selector' => 'ul, ol, li, [data-preview-role="list"]', 'purpose' => 'Structured list content.'],
                ['role' => 'table_content', 'selector' => 'table, th, td, [data-preview-role="table"]', 'purpose' => 'Compact tabular sample content.'],
            ],
            'sample_html' => self::html(),
            'resolution_example' => [
                'context' => $exampleContext,
                'tokens' => app(DesignTokenResolver::class)->resolve($exampleContext),
            ],
        ];
    }

    public static function html(?Theme $theme = null, ?array $context = null): string
    {
        return self::htmlForCss($theme?->cssForInjection() ?? '', $context);
    }

    public static function htmlForCss(string $themeCss, ?array $context = null): string
    {
        $html = <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing: border-box; }
html, body { margin: 0; min-height: 100%; }
body {
  background: linear-gradient(180deg, #111827, #1f2937);
  color: #f8fafc;
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
main { min-height: 100vh; padding: 22px; }
.topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 22px; }
.brand { display: flex; align-items: center; gap: 10px; font-weight: 800; }
.mark { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 8px; background: #38bdf8; color: #082f49; }
.nav { display: flex; flex-wrap: wrap; gap: 8px; }
.nav a { color: inherit; font-size: 12px; font-weight: 700; opacity: .76; text-decoration: none; }
.label { font-size: 11px; font-weight: 700; letter-spacing: .08em; opacity: .72; text-transform: uppercase; }
.hero { display: grid; gap: 16px; margin-bottom: 16px; }
h1 { margin: 3px 0 0; font-size: 32px; line-height: 1.04; }
p { margin: 0; }
.lead { max-width: 62ch; color: inherit; font-size: 15px; line-height: 1.55; opacity: .78; }
.actions { display: flex; flex-wrap: wrap; gap: 10px; }
.status, .badge, button, .button {
  border: 1px solid #2563eb;
  border-radius: 8px;
  background: #2563eb;
  color: white;
  font-size: 12px;
  font-weight: 700;
  padding: 8px 12px;
}
.status, .badge { border-radius: 999px; padding: 6px 10px; }
.button.secondary, button.secondary {
  border-color: #cbd5e1;
  background: white;
  color: #0f172a;
}
.grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(18rem, .9fr); gap: 14px; }
.stack { display: grid; gap: 14px; }
.panel, .card {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: white;
  color: #0f172a;
  padding: 16px;
}
.bar { height: 10px; border-radius: 999px; background: linear-gradient(90deg, #1d4ed8, #38bdf8); margin-bottom: 14px; }
.metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
.metric { min-height: 52px; border-radius: 6px; background: #e2e8f0; padding: 9px; }
.metric strong { display: block; font-size: 20px; line-height: 1; }
.metric span { display: block; margin-top: 7px; font-size: 10px; opacity: .68; text-transform: uppercase; }
.spark { height: 7px; border-radius: 999px; background: #38bdf8; margin-top: 10px; }
.warn, .success { border: 1px solid #f59e0b; border-radius: 6px; background: #fef3c7; color: #7c2d12; padding: 8px 10px; font-size: 12px; font-weight: 650; }
.success { border-color: #22c55e; background: #dcfce7; color: #14532d; }
.form-grid { display: grid; gap: 10px; }
label { display: grid; gap: 5px; font-size: 12px; font-weight: 700; }
input, select, textarea {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: white;
  color: #0f172a;
  font: inherit;
  font-size: 13px;
  padding: 8px 10px;
}
textarea { min-height: 70px; resize: vertical; }
ul { margin: 0; padding-left: 18px; }
li { margin: 5px 0; }
table { width: 100%; margin-top: 12px; border-collapse: collapse; font-size: 11px; }
th { text-align: left; opacity: .7; }
td, th { padding: 5px 0; border-bottom: 1px solid rgba(100, 116, 139, .22); }
@media (max-width: 760px) {
  main { padding: 16px; }
  .grid { grid-template-columns: 1fr; }
  .metrics { grid-template-columns: 1fr; }
  h1 { font-size: 26px; }
}
</style>
<style data-growth-theme-preview>
HTML
            ."\n".$themeCss."\n"
            .<<<'HTML'
</style>
</head>
<body data-preview-role="chrome">
<main data-preview-role="chrome">
  <div class="topbar">
    <div class="brand">
      <div class="mark">G</div>
      <span>Growth Theme</span>
    </div>
    <nav class="nav" aria-label="Sample navigation">
      <a role="link" tabindex="0" aria-disabled="true">Overview</a>
      <a role="link" tabindex="0" aria-disabled="true">Work</a>
      <a role="link" tabindex="0" aria-disabled="true">Evidence</a>
    </nav>
  </div>

  <section class="hero">
    <div>
      <div class="label" data-preview-role="kicker">Theme specimen</div>
      <h1 data-preview-role="title">Interface sample</h1>
    </div>
    <p class="lead">A representative product surface for checking typography, chrome, buttons, forms, cards, states, tables, and dense operational content in one place.</p>
    <div class="actions">
      <button type="button" class="primary" data-preview-role="primary-action">Primary action</button>
      <button type="button" class="secondary" data-preview-role="secondary-action">Secondary action</button>
      <span class="status" data-preview-role="active-status">Live</span>
    </div>
  </section>

  <section class="grid">
    <div class="stack">
      <section class="panel" data-preview-role="panel">
        <div class="bar" data-preview-role="bar"></div>
        <div class="metrics">
          <div class="metric" data-preview-role="metric"><strong>42</strong><span>primary</span></div>
          <div class="metric" data-preview-role="metric"><strong>18</strong><span>secondary</span></div>
          <div class="metric" data-preview-role="metric"><strong>7</strong><span>warning</span></div>
        </div>
        <div class="spark" data-preview-role="spark"></div>
      </section>

      <section class="panel" data-preview-role="form">
        <div class="label">Form controls</div>
        <div class="form-grid">
          <label>Project name <input value="Apollo checkout" aria-label="Project name"></label>
          <label>Status <select aria-label="Status"><option>In review</option><option>Ready</option></select></label>
          <label>Notes <textarea aria-label="Notes">Theme should keep dense content readable.</textarea></label>
        </div>
      </section>

      <section class="panel">
        <div class="label">Table</div>
        <table data-preview-role="table">
          <thead><tr><th>Element</th><th>State</th><th>Owner</th></tr></thead>
          <tbody>
            <tr><td>Navigation</td><td>Active</td><td>Design</td></tr>
            <tr><td>Form</td><td>Ready</td><td>Product</td></tr>
            <tr><td>Alert</td><td>Review</td><td>Engineering</td></tr>
          </tbody>
        </table>
      </section>
    </div>

    <aside class="stack">
      <section class="card">
        <div class="label">Badges</div>
        <p>
          <span class="badge" data-preview-role="badge">Default</span>
          <span class="badge" data-preview-role="badge">Selected</span>
        </p>
      </section>

      <section class="card">
        <div class="label">States</div>
        <div class="stack">
          <div class="success" data-preview-role="success">Success state</div>
          <div class="warn" data-preview-role="warning">Attention state</div>
        </div>
      </section>

      <section class="card">
        <div class="label">List content</div>
        <ul data-preview-role="list">
          <li>Readable body text inside compact panels</li>
          <li>Clear spacing around repeated rows</li>
          <li>Visible focus and action contrast</li>
        </ul>
      </section>
    </aside>
  </section>
</main>
</body>
</html>
HTML;

        if ($context !== null) {
            $html = str_replace('</main>', self::buildResolutionPanel($context).'</main>', $html);
        }

        return $html;
    }

    /**
     * @param  array<string,string>  $context
     */
    private static function buildResolutionPanel(array $context): string
    {
        $tokens = app(DesignTokenResolver::class)->resolve($context);
        $contextJson = htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT) ?: '{}', ENT_QUOTES);
        $rows = '';

        foreach ($tokens as $token => $data) {
            $rows .= '<tr><td>'.htmlspecialchars($token, ENT_QUOTES).'</td>'
                .'<td><code>'.htmlspecialchars($data['value'], ENT_QUOTES).'</code></td>'
                .'<td>'.htmlspecialchars($data['source'], ENT_QUOTES).'</td>'
                .'<td>'.htmlspecialchars($data['reason'], ENT_QUOTES).'</td></tr>';
        }

        return <<<HTML
<section class="panel" style="margin-top:14px" data-preview-role="token-resolution-panel">
  <div class="label">Resolved design tokens</div>
  <details style="margin-bottom:8px;font-size:11px"><summary>Context</summary><pre>{$contextJson}</pre></details>
  <table><thead><tr><th>Token</th><th>Value</th><th>Source</th><th>Reason</th></tr></thead><tbody>{$rows}</tbody></table>
</section>
HTML;
    }

    public static function contractMarkdown(): string
    {
        $contract = self::contract();
        $markdown = "### Theme Preview Selector Contract\n\n";

        foreach ($contract['guidance'] as $item) {
            $markdown .= "- {$item}\n";
        }

        $markdown .= "\nStable selectors:\n";
        foreach ($contract['selectors'] as $selector) {
            $markdown .= "- **{$selector['role']}**: `{$selector['selector']}` - {$selector['purpose']}\n";
        }

        return $markdown."\n";
    }

    /**
     * @param  array<string,mixed>|null  $tokens
     * @return list<array{code:string,message:string}>
     */
    public static function qualityWarnings(?array $tokens, ?string $rawCss): array
    {
        $warnings = [];
        $normalizedTokens = self::normalizeTokenMap($tokens);
        $text = self::firstColor([
            $normalizedTokens['text'] ?? null,
            self::cssDeclaration($rawCss, 'body', 'color'),
            self::cssDeclaration($rawCss, 'main', 'color'),
        ]);
        $background = self::firstColor([
            $normalizedTokens['surface'] ?? null,
            self::cssDeclaration($rawCss, 'body', 'background-color'),
            self::cssDeclaration($rawCss, 'body', 'background'),
            self::cssDeclaration($rawCss, 'main', 'background-color'),
            self::cssDeclaration($rawCss, 'main', 'background'),
        ]);

        if ($text !== null && $background !== null && self::contrastRatio($text, $background) < 4.5) {
            $warnings[] = [
                'code' => 'preview_text_contrast',
                'message' => 'Theme preview title/kicker text may have low contrast against the preview chrome. Adjust the text and surface/chrome color relationship, or target h1/.label/[data-preview-role="title"] explicitly.',
            ];
        }

        return $warnings;
    }

    /**
     * @param  array<string,mixed>|null  $tokens
     * @return array<string,string>
     */
    private static function normalizeTokenMap(?array $tokens): array
    {
        $normalized = [];

        foreach ($tokens ?? [] as $name => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $token = strtolower(trim((string) $name));
            $token = preg_replace('/^--/', '', $token) ?? $token;
            $normalized[$token] = trim((string) $value);
        }

        return $normalized;
    }

    /**
     * @param  list<string|null>  $values
     */
    private static function firstColor(array $values): ?string
    {
        foreach ($values as $value) {
            $color = self::parseColor($value);

            if ($color !== null) {
                return $color;
            }
        }

        return null;
    }

    private static function cssDeclaration(?string $css, string $selector, string $property): ?string
    {
        if (! is_string($css) || trim($css) === '') {
            return null;
        }

        if (preg_match('/'.preg_quote($selector, '/').'\s*\{(?P<body>[^}]*)\}/i', $css, $match) !== 1) {
            return null;
        }

        if (preg_match('/'.preg_quote($property, '/').'\s*:\s*(?P<value>[^;]+);?/i', $match['body'], $declaration) !== 1) {
            return null;
        }

        return trim($declaration['value']);
    }

    private static function contrastRatio(string $foreground, string $background): float
    {
        $foregroundLuminance = self::relativeLuminance($foreground);
        $backgroundLuminance = self::relativeLuminance($background);
        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);
        $channels = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }, [$r, $g, $b]);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function parseColor(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        if (preg_match('/#[0-9a-fA-F]{6}\b/', $value, $match) === 1) {
            return $match[0];
        }

        if (preg_match('/#[0-9a-fA-F]{3}\b/', $value, $match) === 1) {
            return $match[0];
        }

        return null;
    }
}
