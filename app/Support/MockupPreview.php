<?php

namespace App\Support;

use App\Models\Mockup;
use App\Models\MockupRevision;
use App\Models\Project;
use App\Models\Theme;

class MockupPreview
{
    public function html(Mockup $mockup, MockupRevision $revision, string $themeSlug = ''): string
    {
        $html = $this->applyLayout((string) $revision->html, $mockup);

        return $this->makePreviewInert(
            MockupHtml::withoutOwnerReference(
                $this->applyTheme($html, $mockup, $themeSlug),
                $mockup->owner,
            ),
        );
    }

    public function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'none'",
            "script-src 'unsafe-inline' 'unsafe-eval' https:",
            "style-src 'unsafe-inline' https:",
            'img-src data: https:',
            'font-src data: https:',
            "connect-src 'none'",
            "form-action 'none'",
            "navigate-to 'none'",
            "frame-ancestors 'self'",
            "base-uri 'none'",
            'sandbox allow-scripts',
        ]);
    }

    private function applyLayout(string $html, Mockup $mockup): string
    {
        if ($mockup->owner_type === 'project') {
            return $html;
        }

        $owner = $mockup->owner;
        $projectId = $owner?->project_id;

        if (! is_string($projectId)) {
            return $html;
        }

        $layout = Mockup::where('owner_type', 'project')
            ->where('owner_id', $projectId)
            ->where('name', 'layout')
            ->with('currentRevision')
            ->first();

        if (! $layout?->currentRevision) {
            return $html;
        }

        $layoutHtml = (string) $layout->currentRevision->html;

        if (! str_contains($layoutHtml, 'id="growth-content"')) {
            return $html;
        }

        $content = $this->extractBodyContent($html);

        return preg_replace(
            '/<div\b[^>]*\bid="growth-content"[^>]*>.*?<\/div>/si',
            '<div id="growth-content">'.$content.'</div>',
            $layoutHtml,
            1,
        ) ?? $html;
    }

    private function extractBodyContent(string $html): string
    {
        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/si', $html, $matches) === 1) {
            return trim($matches[1]);
        }

        return $html;
    }

    private function applyTheme(string $html, Mockup $mockup, string $themeSlug): string
    {
        $themeSlug = trim($themeSlug);

        if ($themeSlug === '') {
            return $html;
        }

        $owner = $mockup->owner;
        $projectId = $owner instanceof Project ? $owner->id : $owner?->project_id;

        if (! is_string($projectId)) {
            return $html;
        }

        $theme = Theme::query()
            ->where('project_id', $projectId)
            ->where('slug', $themeSlug)
            ->first();

        if (! $theme) {
            return $html;
        }

        $style = $theme->styleElement();

        if ($style === '') {
            return $html;
        }

        if (preg_match('/<\/head>/i', $html) === 1) {
            return preg_replace('/<\/head>/i', $style."\n".'$0', $html, 1) ?? $html;
        }

        if (preg_match('/<html\b[^>]*>/i', $html) === 1) {
            return preg_replace('/<html\b[^>]*>/i', '$0'."\n<head>\n".$style."\n</head>", $html, 1) ?? $html;
        }

        return $style."\n".$html;
    }

    private function makePreviewInert(string $html): string
    {
        $script = <<<'HTML'
<script data-growth-preview-inert>
(() => {
  document.addEventListener('click', (event) => {
    if (event.target && event.target.closest('a[href]')) {
      event.preventDefault();
    }
  }, true);

  document.addEventListener('submit', (event) => {
    event.preventDefault();
  }, true);
})();
</script>
HTML;

        if (str_contains($html, 'data-growth-preview-inert')) {
            return $html;
        }

        if (preg_match('/<\/body>/i', $html) === 1) {
            return preg_replace('/<\/body>/i', $script."\n".'$0', $html, 1) ?? $html;
        }

        return $html."\n".$script;
    }
}
