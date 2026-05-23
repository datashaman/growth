<?php

namespace App\Mcp\Resources\Concerns;

use App\Models\SpecMockup;
use App\Models\SpecMockupRevision;
use App\Support\MockupPreview;
use App\Support\MockupPreviewInspector;
use App\Support\MockupThemeResolver;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Throwable;

trait InspectsMockups
{
    use ReturnsStructuredJson;

    protected function inspectionResponse(Request $request, ?string $revisionId = null): Response
    {
        $mockupId = $this->pathValue((string) $request->get('mockup'));
        $revisionId = $revisionId === null ? null : $this->pathValue($revisionId);
        $query = $this->query($request);
        $requestedTheme = (string) ($query['theme'] ?? 'assigned');

        $mockup = $this->mockup($mockupId);

        if (! $mockup) {
            return Response::error("Mockup [{$mockupId}] not found.");
        }

        $revision = $revisionId === null
            ? $mockup->currentRevision
            : $mockup->revisions->firstWhere('id', $revisionId);

        if (! $revision) {
            return $revisionId === null
                ? Response::error("Mockup [{$mockupId}] has no revisions yet.")
                : Response::error("Revision [{$revisionId}] not found on mockup [{$mockupId}].");
        }

        $theme = app(MockupThemeResolver::class)->resolve($mockup, $requestedTheme);

        if ($requestedTheme !== 'assigned' && $requestedTheme !== 'none' && $theme['slug'] === null) {
            return Response::error("Theme [{$requestedTheme}] not found for mockup [{$mockupId}].");
        }

        try {
            $inspection = app(MockupPreviewInspector::class)->inspect(
                $this->html($mockup, $revision, $theme)
            );
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        return $this->json([
            'type' => 'mockup_preview',
            'mockup_id' => $mockup->id,
            'revision_id' => $revision->id,
            'revision_number' => $revision->number,
            'preview_url' => $this->previewUrl($mockup, $revision, $theme),
            'theme' => $theme,
            'visible_text' => $inspection['visible_text'],
            'warnings' => $inspection['warnings'],
            'screenshot' => [
                'uri' => $this->screenshotUri($mockup, $revision, $requestedTheme),
                'mime_type' => 'image/png',
            ],
        ]);
    }

    protected function screenshotResponse(Request $request): Response
    {
        $mockupId = $this->pathValue((string) $request->get('mockup'));
        $revisionId = $this->pathValue((string) $request->get('revision'));
        $query = $this->query($request);
        $requestedTheme = (string) ($query['theme'] ?? $request->get('theme', 'assigned'));

        $mockup = $this->mockup($mockupId);

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

        try {
            $screenshot = app(MockupPreviewInspector::class)->screenshot(
                $this->html($mockup, $revision, $theme)
            );
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::image($screenshot['content'], 'image/png');
    }

    private function mockup(string $mockupId): ?SpecMockup
    {
        return SpecMockup::with([
            'currentRevision',
            'owner.project.themes',
            'owner.project.themeAssignments.theme',
            'revisions',
        ])->find($mockupId);
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    private function html(SpecMockup $mockup, SpecMockupRevision $revision, array $theme): string
    {
        return app(MockupPreview::class)->html($mockup, $revision, (string) ($theme['slug'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    private function previewUrl(SpecMockup $mockup, SpecMockupRevision $revision, array $theme): string
    {
        $query = ['mockup' => $mockup, 'revision' => $revision->id];

        if ($theme['slug'] !== null) {
            $query['theme'] = $theme['slug'];
        }

        return route('mockups.raw', $query);
    }

    private function screenshotUri(SpecMockup $mockup, SpecMockupRevision $revision, string $requestedTheme): string
    {
        $uri = "growth://mockups/{$mockup->id}/{$revision->id}/screenshot";

        return $requestedTheme === 'assigned'
            ? $uri
            : $uri.'?'.http_build_query(['theme' => $requestedTheme]);
    }

    /**
     * @return array<string, string>
     */
    private function query(Request $request): array
    {
        $query = parse_url((string) $request->uri(), PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return [];
        }

        parse_str($query, $params);

        return array_filter(
            $params,
            fn (mixed $value): bool => is_string($value),
        );
    }

    private function pathValue(string $value): string
    {
        return explode('?', $value, 2)[0];
    }
}
