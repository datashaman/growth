<?php

namespace App\Support;

use App\Models\SpecMockup;
use App\Models\SpecMockupRevision;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

class MockupScreenshotAsset
{
    /**
     * @return array{url:string,mcp_url:string,mime_type:string,theme:string}
     */
    public function reference(SpecMockup $mockup, SpecMockupRevision $revision, string $requestedTheme = 'assigned'): array
    {
        $requestedTheme = $this->requestedTheme($requestedTheme);

        return [
            'url' => URL::signedRoute('mockups.screenshot', [
                'mockup' => $mockup->id,
                'revision' => $revision->id,
                'theme' => $requestedTheme,
            ]),
            'mcp_url' => route('api.mockup-shots.show', [
                'mockup' => $mockup->id,
                'revision' => $revision->id,
                'theme' => $requestedTheme,
            ]),
            'mime_type' => 'image/png',
            'theme' => $requestedTheme,
        ];
    }

    /**
     * @return array{content:string,width:int,height:int}
     */
    public function render(SpecMockup $mockup, SpecMockupRevision $revision, string $requestedTheme = 'assigned'): array
    {
        $theme = $this->theme($mockup, $requestedTheme);

        return app(MockupPreviewInspector::class)->screenshot(
            app(MockupPreview::class)->html($mockup, $revision, (string) ($theme['slug'] ?? ''))
        );
    }

    /**
     * @return array{requested:string,resolved:string,slug:?string,id:?string,name:?string}
     */
    public function theme(SpecMockup $mockup, string $requestedTheme = 'assigned'): array
    {
        $requestedTheme = $this->requestedTheme($requestedTheme);
        $theme = app(MockupThemeResolver::class)->resolve($mockup, $requestedTheme);

        if ($requestedTheme !== 'assigned' && $requestedTheme !== 'none' && $theme['slug'] === null) {
            throw new InvalidArgumentException("Theme [{$requestedTheme}] not found for mockup [{$mockup->id}].");
        }

        return $theme;
    }

    private function requestedTheme(string $requestedTheme): string
    {
        $requestedTheme = trim($requestedTheme);

        return $requestedTheme === '' ? 'assigned' : $requestedTheme;
    }
}
