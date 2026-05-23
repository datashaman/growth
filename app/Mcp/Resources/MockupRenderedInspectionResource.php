<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\Concerns\ReturnsStructuredJson;
use App\Models\SpecMockup;
use App\Support\MockupPreview;
use App\Support\MockupRenderedInspector;
use App\Support\MockupThemeResolver;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;
use Throwable;

#[Name('Mockup Rendered Inspection')]
#[Description('Browser-rendered inspection for one spec mockup revision, including preview URL, theme context, visible text, screenshot, and warnings for visible Growth/internal metadata.')]
#[MimeType('application/json')]
class MockupRenderedInspectionResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://mockups/{mockup}/revisions/{revision}/rendered-inspection/{theme}');
    }

    public function handle(Request $request): Response
    {
        $mockupId = (string) $request->get('mockup');
        $revisionId = (string) $request->get('revision');
        $requestedTheme = (string) $request->get('theme', 'assigned');

        $mockup = SpecMockup::with([
            'owner.project.themes',
            'owner.project.themeAssignments.theme',
            'revisions',
        ])->find($mockupId);

        if (! $mockup) {
            return Response::error("Mockup [{$mockupId}] not found.");
        }

        $revision = $mockup->revisions->firstWhere('id', $revisionId);

        if (! $revision) {
            return Response::error("Revision [{$revisionId}] not found on mockup [{$mockupId}].");
        }

        $theme = app(MockupThemeResolver::class)->resolve($mockup, $requestedTheme);

        if ($requestedTheme !== 'assigned' && $requestedTheme !== 'none' && $theme['slug'] === null) {
            return Response::error("Theme [{$requestedTheme}] not found for mockup [{$mockupId}].");
        }

        $query = ['mockup' => $mockup, 'revision' => $revision->id];
        if ($theme['slug'] !== null) {
            $query['theme'] = $theme['slug'];
        }

        $previewUrl = route('mockups.raw', $query);
        $html = app(MockupPreview::class)->html($mockup, $revision, (string) ($theme['slug'] ?? ''));

        try {
            $inspection = app(MockupRenderedInspector::class)->inspect($html);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        return $this->json([
            'type' => 'mockup_rendered_inspection',
            'mockup_id' => $mockup->id,
            'revision_id' => $revision->id,
            'revision_number' => $revision->number,
            'preview_url' => $previewUrl,
            'theme' => $theme,
            'visible_text' => $inspection['visible_text'],
            'warnings' => $inspection['warnings'],
            'screenshot' => $inspection['screenshot'],
        ]);
    }
}
