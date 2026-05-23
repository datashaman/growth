<?php

namespace App\Mcp\Resources;

use App\Models\SpecMockup;
use App\Support\MockupScreenshotAsset;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Mockup Revision Screenshot')]
#[Description('PNG screenshot pixels for a specific spec mockup revision. Pass ?theme=none or ?theme={slug} to override assigned theme.')]
#[MimeType('image/png')]
class MockupRevisionScreenshotResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://mockups/{mockup}/{revision}/screenshot');
    }

    public function handle(Request $request): Response
    {
        $mockupId = $this->pathValue($request->get('mockup'));
        $revisionId = $this->pathValue($request->get('revision'));
        $requestedTheme = $this->pathValue($request->get('theme', $this->query($request)['theme'] ?? 'assigned'));

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
            return Response::error("Revision [{$revisionId}] not found for mockup [{$mockupId}].");
        }

        try {
            $screenshot = app(MockupScreenshotAsset::class)->render($mockup, $revision, $requestedTheme);
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::image($screenshot['content'], 'image/png');
    }

    /**
     * @return array<string,string>
     */
    private function query(Request $request): array
    {
        $query = parse_url((string) $request->uri(), PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $params);

        return array_filter($params, is_string(...));
    }

    private function pathValue(mixed $value): string
    {
        return explode('?', (string) $value, 2)[0];
    }
}
